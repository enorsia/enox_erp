<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\CloudflareFileDeleteJob;
use App\Jobs\CloudflareFileUploadJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('profile.index', compact('user'));
    }

    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);

        try {
            $user = User::findOrFail(Auth::id());

            // Capture old values
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'address' => $user->address,
            ];

            $avatarUpdated = false;

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                @unlink(public_path('upload/user_images/' . $user->avatar));
                CloudflareFileDeleteJob::dispatch(basename('upload/user_images/' . $user->avatar));
                $filename = 'IMG_' . date('YmdHi') . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('upload/user_images'), $filename);
                CloudflareFileUploadJob::dispatch('upload/user_images/'.$filename);
                $user->update([
                    "avatar" => $filename,
                ]);
                $avatarUpdated = true;
            }

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone'  => $request->phone,
                'gender'  => $request->gender,
                'address'  => $request->address
            ]);

            // Capture new values
            $user->refresh();
            $newValues = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'address' => $user->address,
            ];

            // Detect changes
            $changes = [];
            foreach ($oldValues as $key => $oldValue) {
                if ($oldValue != $newValues[$key]) {
                    $changes[] = ucfirst($key);
                }
            }

            if ($avatarUpdated) {
                $changes[] = 'Profile picture';
            }

            // Log only if there are changes
            if (count($changes) > 0) {
                $description = $user->name . ' updated their profile';
                if (count($changes) > 0) {
                    $description .= ' (Changed: ' . implode(', ', $changes) . ')';
                }

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($user)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log($description);
            }

            notify()->success('User Updated Successfully', 'success');
            return redirect()->back();
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            notify()->error('User Update Failed', 'error');
            return back();
        }
    }

    public function changePassword()
    {
        return view('profile.change-password');
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:6',
        ]);

        $user = User::findOrFail(Auth::id());
        $hassedPassword = $user->password;

        if (Hash::check($request->current_password, $hassedPassword)) {
            if (!Hash::check($request->password, $hassedPassword)) {
                $user->update([
                    'password' => Hash::make($request->password)
                ]);

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($user)
                    ->withProperties(['user_name' => $user->name, 'email' => $user->email])
                    ->log($user->name . ' changed their password successfully');

                Auth::logout();
                return redirect()->route('admin.login');
            }else{
                notify()->warning('New password can not be as old password!', 'Warning');
            }
        }else{
            notify()->error('Current password not match!','Error');
        }
        return back();
    }
}
