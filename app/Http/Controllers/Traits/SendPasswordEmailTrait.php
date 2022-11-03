<?php
/**
 * Created by PhpStorm.
 * User: smurray
 * Date: 7/14/18
 * Time: 5:21 PM
 */
namespace App\Http\Controllers\Traits;

use App\Http\Controllers\Api\Project\EmailController;
use Illuminate\Support\Facades\Mail;

trait SendPasswordEmailTrait {

    function sendPasswordEmail($user ) {
        $ec = new EmailController();

        $isNewUser = intval($user->ytd_logins) === 0;

        $fromEmail = $ec->mailFromAddress();
        $fromName = $ec->mailFromName();

        if ($isNewUser) {
            $subject = "Precision IT Site Registration";
        } else {
            $subject = "Precision IT - Reset Password";
        }

        $token = md5($user->email . time() . $this->getRandomString());
        $host = request()->getHttpHost();
        $forwardHost = request()->header('X-Forwarded-Host');
        if (strstr(strtolower($forwardHost), 'localhost')) {
            $host = "localhost:8080";
        } else {
            // Get host from request and make sure we point to the public site
            $host = str_replace("admin", "public", $host);
        }
        $websiteUrl = request()->getScheme() . "://" . $host . "/";

        if ($isNewUser) {
            $body = "You have been invited to use this app. Please follow the link provided:

" . $websiteUrl . "registration?token=" . $token . '&email=' . urlencode($user->email);
        } else {
            $link = env('UI_V2', false) ? 'reset-password' : 'registration';

            $body = "You have requested a password reset for your account. Please follow the link provided:

" . $websiteUrl . $link . "?token=" . $token . '&email=' . urlencode($user->email)."&reset=1";
        }

        $email = $user->email;
        $user->resetHash = $token;
        $user->save();
        Mail::raw($body, function($message) use($email, $fromEmail, $fromName, $subject)
        {
            //$user = Auth::user();
            $message->from($fromEmail, $fromName);
            //$message->replyTo($user->email, $user->username);

            $message->to($email)->subject($subject);
        });
    }

    public function getRandomString($valid_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $length = 15)
    {
        // start with an empty random string
        $random_string = "";

        // count the number of chars in the valid chars string so we know how many choices we have
        $num_valid_chars = strlen($valid_chars);

        // repeat the steps until we've created a string of the right length
        for ($i = 0; $i < $length; $i++) {
            // pick a random number from 1 up to the number of valid chars
            $random_pick = mt_rand(1, $num_valid_chars);

            // take the random character out of the string of valid chars
            // subtract 1 from $random_pick because strings are indexed starting at 0, and we started picking at 1
            $random_char = $valid_chars[$random_pick - 1];

            // add the randomly-chosen char onto the end of our string so far
            $random_string .= $random_char;
        }

        // return our finished random string
        return $random_string;
    }
}