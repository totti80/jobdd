<?php

namespace App\Http\Requests;

use App\Models\ContactInquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'subject' => ['required', 'string', Rule::in(ContactInquiry::CATEGORIES)],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attributeを入力してください。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
            'subject.in' => 'お問い合わせ種別を選択肢から選んでください。',
            'email.email' => 'メールアドレスを正しい形式で入力してください。',
            'not_regex' => ':attributeに改行を含めないでください。',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'お名前', 'email' => 'メールアドレス', 'subject' => 'お問い合わせ種別', 'message' => 'お問い合わせ内容'];
    }
}
