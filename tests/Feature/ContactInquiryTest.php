<?php

use App\Mail\ContactInquiryReceipt;
use App\Mail\ContactInquiryReceived;
use App\Models\ContactInquiry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

function contactInquiryInput(array $overrides = []): array
{
    return [...[
        'name' => '問い合わせ 太郎',
        'email' => 'visitor@example.com',
        'subject' => '企業掲載について',
        'message' => "企業掲載について質問があります。\n登録方法を教えてください。",
    ], ...$overrides];
}

beforeEach(function () {
    Mail::fake();
    config(['jobdd.contact_notification_email' => 'postmaster@jobdd.jp']);
});

test('contact page is public and renders accessible fields csrf and navigation', function () {
    $response = $this->get(route('public.contact'))->assertOk()->assertViewIs('public.contact')
        ->assertHeader('Cache-Control', 'no-store, private')->assertSee('お問い合わせを送信する')
        ->assertSee('name="_token"', false)->assertSee(route('public.contact.store'), false)
        ->assertDontSee('現在準備中')->assertDontSee('postmaster@')->assertDontSee('postmaster@jobdd.jp');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    foreach (['name', 'email', 'subject', 'message'] as $field) {
        expect($xpath->query('//label[@for="contact-'.$field.'"]')->length)->toBe(1);
        expect($xpath->query('//*[@id="contact-'.$field.'" and @required]')->length)->toBe(1);
    }
    expect($xpath->query('//header//nav/a[@aria-current="page"]')->item(0)->textContent)->toBe('お問い合わせ');
    expect($xpath->query('//input[@name="subject"]')->length)->toBe(0);
    expect($xpath->query('//select[@name="subject"]/option[@value!=""]')->length)->toBe(5);
    $this->assertDatabaseCount('contact_inquiries', 0);
    Mail::assertNothingSent();
});

test('each category is stored and sends admin and receipt emails with correct headers', function (string $category) {
    $this->post(route('public.contact.store'), contactInquiryInput(['subject' => $category, 'notification_sent_at' => '2000-01-01']))
        ->assertRedirect(route('public.contact'))->assertSessionHas('contact_sent', true)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('contact_inquiries', 1);
    $this->assertDatabaseHas('contact_inquiries', contactInquiryInput(['subject' => $category]));
    $inquiry = ContactInquiry::sole();
    expect($inquiry->notification_sent_at)->not->toBeNull();
    Mail::assertSent(ContactInquiryReceived::class, function ($mail) use ($inquiry) {
        $envelope = $mail->envelope();

        return $mail->hasTo('postmaster@jobdd.jp') && $mail->inquiry->id === $inquiry->id
            && $envelope->replyTo[0]->address === 'visitor@example.com'
            && $envelope->subject === '[JobDDお問い合わせ] '.$inquiry->subject
            && $envelope->from->address === config('mail.from.address')
            && $envelope->from->name === config('mail.from.name');
    });
    Mail::assertSent(ContactInquiryReceipt::class, function ($mail) use ($inquiry) {
        $envelope = $mail->envelope();

        return $mail->hasTo('visitor@example.com') && $mail->inquiry->id === $inquiry->id
            && $envelope->replyTo[0]->address === 'postmaster@jobdd.jp'
            && $envelope->subject === '【JobDD】お問い合わせを受け付けました'
            && $envelope->from->address === config('mail.from.address')
            && $envelope->from->name === config('mail.from.name');
    });
    Mail::assertSentCount(2);
    $this->get(route('public.contact'))->assertSee('お問い合わせを受け付けました。');
    $this->get(route('public.contact'))->assertDontSee('お問い合わせを受け付けました。');
    $this->assertDatabaseCount('contact_inquiries', 1);
})->with(ContactInquiry::CATEGORIES);

test('invalid inquiry is rejected without storage or notification and retains input', function (array $input, array $fields) {
    $this->from(route('public.contact'))->post(route('public.contact.store'), $input)
        ->assertRedirect(route('public.contact'))->assertSessionHasErrors($fields)->assertSessionMissing('contact_sent');
    $this->assertDatabaseCount('contact_inquiries', 0);
    Mail::assertNothingSent();
    // Carry the browser session cookie so JSON error bags reload from persisted data.
    $this->withCookie(config('session.cookie'), $this->app['session.store']->getId());
    $this->get(route('public.contact'))->assertOk()->assertSee('送信できませんでした。');
})->with([
    'missing' => [[], ['name', 'email', 'subject', 'message']],
    'whitespace' => [contactInquiryInput(['message' => '   ']), ['message']],
    'invalid email' => [contactInquiryInput(['email' => 'invalid']), ['email']],
    'unknown category' => [contactInquiryInput(['subject' => '自由入力の件名']), ['subject']],
    'empty category' => [contactInquiryInput(['subject' => '']), ['subject']],
    'arrays' => [contactInquiryInput(['name' => ['bad'], 'email' => ['bad'], 'subject' => ['bad'], 'message' => ['bad']]), ['name', 'email', 'subject', 'message']],
    'too long' => [contactInquiryInput(['name' => str_repeat('名', 101), 'email' => str_repeat('a', 250).'@example.com', 'subject' => str_repeat('件', 201), 'message' => str_repeat('文', 5001)]), ['name', 'email', 'subject', 'message']],
    'header injection' => [contactInquiryInput(['name' => "Name\r\nBcc: bad@example.com", 'email' => "visitor@example.com\r\nBcc: bad@example.com", 'subject' => "Subject\r\nBcc: bad@example.com"]), ['name', 'email', 'subject']],
]);

test('validation preserves escaped input and associates errors with fields', function () {
    $this->from(route('public.contact'))->post(route('public.contact.store'), contactInquiryInput(['name' => '<script>alert(1)</script>', 'email' => 'invalid']))
        ->assertSessionHasErrors('email')->assertSessionHasInput('subject', '企業掲載について');
    $this->withCookie(config('session.cookie'), $this->app['session.store']->getId());
    $this->get(route('public.contact'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('aria-describedby="contact-email-error"', false);
});

test('each mail failure preserves inquiry and still attempts the other mail', function (bool $adminFails, bool $receiptFails) {
    Mail::shouldReceive('to')->once()->with('postmaster@jobdd.jp')->andReturnSelf();
    Mail::shouldReceive('to')->once()->with('visitor@example.com')->andReturnSelf();
    $admin = Mail::shouldReceive('send')->once()->with(Mockery::type(ContactInquiryReceived::class));
    $receipt = Mail::shouldReceive('send')->once()->with(Mockery::type(ContactInquiryReceipt::class));
    $adminFails ? $admin->andThrow(new RuntimeException('private details')) : $admin->andReturnNull();
    $receiptFails ? $receipt->andThrow(new RuntimeException('private details')) : $receipt->andReturnNull();
    foreach (['notification' => $adminFails, 'receipt' => $receiptFails] as $kind => $fails) {
        if ($fails) {
            Log::shouldReceive('error')->once()->with('Contact inquiry '.$kind.' failed; inquiry remains stored.', Mockery::on(fn ($context) => array_keys($context) === ['contact_inquiry_id', 'exception_class']));
        }
    }
    $this->post(route('public.contact.store'), contactInquiryInput())
        ->assertRedirect(route('public.contact'))->assertSessionHas('contact_sent', true)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('contact_inquiries', 1);
    $this->assertDatabaseHas('contact_inquiries', contactInquiryInput());
    expect(ContactInquiry::sole()->notification_sent_at === null)->toBe($adminFails);
})->with([[true, false], [false, true], [true, true]]);

test('storage failure gives retry guidance and never notifies or reports success', function () {
    ContactInquiry::creating(fn () => throw new QueryException('testing', 'insert private data', [], new RuntimeException('unavailable')));
    Log::shouldReceive('error')->once()->with('Contact inquiry storage failed.', ['exception_class' => QueryException::class]);
    try {
        $this->post(route('public.contact.store'), contactInquiryInput())->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors('contact')->assertSessionMissing('contact_sent')->assertSessionHasInput('email', 'visitor@example.com');
        $this->assertDatabaseCount('contact_inquiries', 0);
        Mail::assertNothingSent();
    } finally {
        ContactInquiry::flushEventListeners();
    }
});

test('contact submissions are rate limited before additional records or emails', function () {
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post(route('public.contact.store'), contactInquiryInput())->assertRedirect(route('public.contact'));
    }
    $this->post(route('public.contact.store'), contactInquiryInput())->assertStatus(429);
    $this->assertDatabaseCount('contact_inquiries', 3);
    Mail::assertSentCount(6);
});

test('both mails escape inquiry content and include category name and received time', function () {
    $inquiry = ContactInquiry::create(contactInquiryInput(['message' => "<script>alert(1)</script>\n次の行"]));
    foreach ([new ContactInquiryReceived($inquiry), new ContactInquiryReceipt($inquiry)] as $mail) {
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $mail->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $mail->assertSeeInHtml('次の行');
        $mail->assertSeeInHtml($inquiry->name);
        $mail->assertSeeInHtml('お問い合わせ種別');
        $mail->assertSeeInHtml($inquiry->subject);
        $mail->assertSeeInHtml($inquiry->created_at->format('Y/m/d H:i:s'));
    }
    (new ContactInquiryReceipt($inquiry))->assertSeeInHtml('このメールはお問い合わせ受付の控えです。');
});

test('database sessions preserve csrf and errors across a first visit and submission', function () {
    config(['session.driver' => 'database']);
    $this->app->instance('env', 'local');
    $page = $this->get(route('public.contact'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$page->getContent());
    $token = (new DOMXPath($dom))->query('//input[@name="_token"]')->item(0)->getAttribute('value');
    foreach ($page->headers->getCookies() as $cookie) {
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }
    $this->post(route('public.contact.store'), contactInquiryInput())->assertStatus(419);
    $this->assertDatabaseCount('contact_inquiries', 0);
    $this->from(route('public.contact'))->post(route('public.contact.store'), [...contactInquiryInput(['email' => 'invalid']), '_token' => $token])
        ->assertRedirect(route('public.contact'))->assertSessionHasErrors('email');
    $this->get(route('public.contact'))->assertOk()->assertSee('メールアドレスを正しい形式で入力してください。')
        ->assertSee('value="企業掲載について" selected', false);
    $this->post(route('public.contact.store'), [...contactInquiryInput(), '_token' => $token])
        ->assertRedirect(route('public.contact'))->assertSessionHas('contact_sent', true);
    $this->get(route('public.contact'))->assertSee('お問い合わせを受け付けました。');
    $this->get(route('public.contact'))->assertDontSee('お問い合わせを受け付けました。');
    $this->assertDatabaseCount('contact_inquiries', 1);
    Mail::assertSentCount(2);
});
