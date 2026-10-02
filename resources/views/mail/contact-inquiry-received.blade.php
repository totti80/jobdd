<p>JobDDでお問い合わせを受け付けました。</p>
<dl>
    <dt>受付番号</dt><dd>{{ $inquiry->id }}</dd>
    <dt>受付日時</dt><dd>{{ $inquiry->created_at->format('Y/m/d H:i:s') }}</dd>
    <dt>お名前</dt><dd>{{ $inquiry->name }}</dd>
    <dt>メールアドレス</dt><dd>{{ $inquiry->email }}</dd>
    <dt>お問い合わせ種別</dt><dd>{{ $inquiry->subject }}</dd>
</dl>
<p>お問い合わせ内容</p>
<div style="white-space: pre-wrap">{{ $inquiry->message }}</div>
