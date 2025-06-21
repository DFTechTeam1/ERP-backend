<?php

namespace Modules\Addon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Addon\Http\Requests\Addon\Create;

class AddonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('addon::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('addon::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return response()->json([
            'data' => $request->all(),
        ]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('addon::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('addon::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
