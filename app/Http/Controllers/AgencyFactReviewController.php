<?php

namespace App\Http\Controllers;

use App\Models\AgencyFact;

class AgencyFactReviewController extends Controller
{
    public function index()
    {
        $facts = AgencyFact::with([
            'agency',
            'source',
        ])
            ->where('verification_status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.agency-facts.index', [
            'facts' => $facts,
        ]);
    }

    public function verify(AgencyFact $agencyFact)
    {
        $agencyFact->update([
            'verification_status' => 'verified',
        ]);

        return redirect()
            ->route('admin.agency-facts.index');
    }

    public function reject(AgencyFact $agencyFact)
    {
        $agencyFact->update([
            'verification_status' => 'rejected',
        ]);

        return redirect()
            ->route('admin.agency-facts.index');
    }
}
