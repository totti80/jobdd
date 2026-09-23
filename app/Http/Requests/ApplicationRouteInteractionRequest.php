<?php

namespace App\Http\Requests;

use App\Models\JobPosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Apply the public route boundary to interaction targets as well as HTML pages. */
class ApplicationRouteInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_query_id' => ['nullable', 'integer', 'exists:user_queries,id'],
            'application_route_id' => ['required', 'integer', Rule::exists('application_routes', 'id')->where(fn ($query) => $query
                ->where('availability_status', 'available')->whereNull('unavailable_at')
                ->whereIn('route_type', ['direct', 'agent', 'platform'])
                ->whereIn('job_posting_id', JobPosting::query()->forPublic()->where('status', 'published')->select('id')->toBase()))],
        ];
    }
}
