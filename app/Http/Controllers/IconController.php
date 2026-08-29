<?php

namespace App\Http\Controllers;

use App\Models\Icon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IconController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $icons = Icon::where('user_id', Auth::id())->get();

        return view('icon.index', compact('icons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('icon.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $new_icon = new Icon();
        $new_icon->user_id = Auth::id();
        $new_icon->title = $request->title;
        $new_icon->description = $request->description;

        if ($request->hasFile('image')) {
            $new_icon->image_path = $request
                ->file('image')
                ->store('icons', 'public');
        }

        $new_icon->save();

        return redirect()->route('dashboard');
    }

    /**
     * Display the specified resource.
     */
    public function show(Icon $icon)
    {
        //
    }

/**
 * Show the form for editing the specified resource.
 */
public function edit($id)
{
    $icon = Icon::findOrFail($id);

    if ($icon->user_id !== Auth::id()) {
        return redirect()->route('dashboard');
    }

    return view('icon.edit', compact('icon'));
}

/**
 * Update the specified resource in storage.
 */
public function update(Request $request, Icon $icon)
{
    //
}

/**
 * Remove the specified resource from storage.
 */
public function destroy($id)
{
    $icon = Icon::findOrFail($id);

    if ($icon->user_id !== Auth::id()) {
        return redirect()->route('dashboard');
    }

    $icon->delete();

    return redirect()->route('dashboard');
}
}