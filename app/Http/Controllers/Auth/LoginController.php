<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Token;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        //retrieveByCredentials
        if ($user = app('auth')->getProvider()->retrieveByCredentials($request->only('email', 'password'))) {
            $token = Token::create([
                'user_id' => $user->id
            ]);

            if ($token->sendCode()) {
                session()->set("token_id", $token->id);
                session()->set("user_id", $user->id);
                session()->set("remember", $request->get('remember'));

                return redirect("code");
            }

            $token->delete();// delete token because it can't be sent
            return redirect('/login')->withErrors([
                "Unable to send verification code"
            ]);
        }

        return redirect()->back()
            ->withInputs()
            ->withErrors([
                $this->username() => Lang::get('auth.failed'),
            ]);
    }

    /**
     * Show second factor form
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function showCodeForm()
    {
        if (! session()->has("token_id")) {
            return redirect("login");
        }

        return view("auth.code");
    }

    /**
     * Store and verify user second factor.
     */
    public function storeCodeForm(Request $request)
    {
        // throttle for too many attempts
        if (! session()->has("token_id", "user_id")) {
            return redirect("login");
        }

        $token = Token::find(session()->get("token_id"));
        if (! $token ||
            ! $token->isValid() ||
            $request->code !== $token->code ||
            (int)session()->get("user_id") !== $token->user->id
        ) {
            return redirect("code")->withErrors(["Invalid token"]);
        }

        $token->used = true;
        $token->save();
        $this->guard()->login($token->user, session()->get('remember', false));

        session()->forget('token_id', 'user_id', 'remember');

        return redirect('home');
    }
}
