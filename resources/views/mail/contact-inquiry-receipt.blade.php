<p>JobDDへお問い合わせいただき、ありがとうございます。以下の内容で受け付けました。</p>
<dl>
    <dt>お名前</dt><dd>{{ $inquiry->name }}</dd>
    <dt>お問い合わせ種別</dt><dd>{{ $inquiry->subject }}</dd>
    <dt>受付日時</dt><dd>{{ $inquiry->created_at->format('Y/m/d H:i:s') }}</dd>
</dl>
<p>お問い合わせ内容</p>
<div style="white-space: pre-wrap">{{ $inquiry->message }}</div>
<p>このメールはお問い合わせ受付の控えです。内容を確認のうえ、担当者からご連絡します。</p>
