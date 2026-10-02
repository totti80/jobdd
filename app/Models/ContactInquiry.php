<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    public const CATEGORIES = ['JobDDについて', '求人情報について', '企業掲載について', '不具合・改善要望', 'その他'];

    protected $fillable = ['name', 'email', 'subject', 'message'];

    protected function casts(): array
    {
        return ['notification_sent_at' => 'datetime'];
    }
}
