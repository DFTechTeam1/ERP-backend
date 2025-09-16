<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function markAsRead(string $id)
    {
        $user = Auth::user();
        $employee = \Modules\Hrd\Models\Employee::find($user->employee_id);

        foreach ($employee->unreadNotifications as $notification) {
            if ($notification->id == $id) {
                $notification->markAsRead();
            }
        }

        // from users
        $userData = \App\Models\User::find($user->id);
        foreach ($userData->unreadNotifications as $notification) {
            if ($notification->id == $id) {
                $notification->markAsRead();
            }
        }

        $output = formatNotifications($employee->unreadNotifications->toArray());

        return apiResponse(
            generalResponse(
                'success',
                false,
                $output,
            ),
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function readAll()
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        if ($user->employee_id) {
            $employee = \Modules\Hrd\Models\Employee::find($user->employee_id);

            $employee->unreadNotifications->markAsRead();
        }

        return apiResponse(
            generalResponse(
                message: 'Success'
            )
        );
    }
}
