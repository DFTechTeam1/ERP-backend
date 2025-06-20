<?php

namespace Modules\LineMessaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('linemessaging::index');
    }

    public function webhook(Request $request)
    {
        logging('webhook line', [
            'response' => $request->all(),
        ]);

        $service = new \Modules\LineMessaging\Services\LineConnectionService;

        return $service->webhook($request->all());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('linemessaging::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('linemessaging::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('linemessaging::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
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
