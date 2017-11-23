<?php

namespace App\Modules\Contacts\Controllers;

use Nova\Http\Request;
use Nova\Routing\Controller as BaseController;
use Nova\Support\Facades\App;
use Nova\Support\Facades\Auth;
use Nova\Support\Facades\Redirect;
use Nova\Support\Facades\Validator;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Message;
use App\Modules\Contacts\Notifications\MessageSubmitted as MessageSubmittedNotification;
use App\Modules\Content\Traits\ShortcodesTrait;
use App\Modules\Users\Models\User;

use Thunder\Shortcode\Shortcode\ShortcodeInterface as Shortcode;

use ErrorException;


class Contacts extends BaseController
{
    use ShortcodesTrait;


    public function store(Request $request)
    {
        $input = $request->all();

        // Verify the submitted reCAPTCHA
        if (! Auth::check() && ! ReCaptcha::check($request->input('g-recaptcha-response'), $request->ip())) {
            return Redirect::back()->withInput($input)->withStatus(__d('content', 'The reCaptcha verification failed.'), 'danger');
        }

        $path = $request->input('path');

        if (is_null($contact = Contact::findByPath($path))) {
            throw new LogicErrorException('Contact not found.');
        }

        $shortcodes = $this->parseShortcodes($contact->message);

        //
        $rules  = array();
        $labels = array();

        foreach ($shortcodes as $shortcode) {
            $element = $shortcode->getName();

            if ($element == 'option') {
                // A select option.
                continue;
            }

            // An element with name parameter.
            else if ($shortcode->hasParameter('name')) {
                $name = $shortcode->getParameter('name');
            } else {
                throw new ErrorException('Invalid shorcode.');
            }

            $type = $shortcode->getParameter('type');

            if (($element == 'input') && ($type == 'submit')) {
                // This is the submit button.
                continue;
            }

            if ($shortcode->hasParameter('label')) {
                $labels[$name] = $shortcode->getParameter('label');
            } else {
                throw new ErrorException('Invalid shorcode.');
            }

            if ($shortcode->hasParameter('validation')) {
                $rules[$name] = $shortcode->getParameter('validation');
            }
        }

        $validator = Validator::make($input, $rules, array(), $labels);

        if ($validator->fails()) {
            return Redirect::back()->withInput($input)->withErrors($validator);
        }

        $message = Message::create(array(
            'author_id'      => Auth::id() ?: 0,
            'content'        => $input['content'],
            'title'          => null,
            'parent_id'      => $contact->id,
            'excerpt'        => null,
            'status'         => 'protected',
            'menu_order'     => 0,
            'type'           => 'contact_message',
            'comment_status' => 'closed',
        ));

        $message->name = $id = $message->id;

        $message->guid = site_url('content/' .$id);

        // Handle the Metadata.
        foreach ($labels as $name => $label) {
            $key = 'contact_' .$name;

            $message->meta->addItem($key, $input[$name]);
        }

        $message->meta->contact_author_ip = $request->ip();
        $message->meta->contact_path      = $path;

        $message->save();

        // Update the Contact's messages count.
        $contact->updateCount();

        // Notify the associated User, if exists.
        $user = User::where('email', $contact->email)->first();

        if (! is_null($user)) {
            $user->notify(new MessageSubmittedNotification($message, $contact, $labels));
        }

        return Redirect::back()
            ->withStatus(__d('content', 'Your message was successfully sent.'), 'success');
    }
}
