<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\User;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\EmailChangeRequested;
use Flarum\User\Event\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Mail\Message;

class EmailConfirmationMailer
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @param \Flarum\Settings\SettingsRepositoryInterface $settings
     * @param Mailer $mailer
     * @param UrlGenerator $url
     * @param Translator $translator
     */
    public function __construct(SettingsRepositoryInterface $settings, Mailer $mailer, UrlGenerator $url, Translator $translator)
    {
        $this->settings = $settings;
        $this->mailer = $mailer;
        $this->url = $url;
        $this->translator = $translator;
    }

    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Registered::class, [$this, 'whenUserWasRegistered']);
        $events->listen(EmailChangeRequested::class, [$this, 'whenUserEmailChangeWasRequested']);
    }

    /**
     * @param \Flarum\User\Event\Registered $event
     */
    public function whenUserWasRegistered(Registered $event)
    {
        $user = $event->user;

        if ($user->is_email_confirmed) {
            return;
        }

        $data = $this->getEmailData($user, $user->email);

        $bodyText = $this->translator->trans('core.email.activate_account.body_text', $data);
        $bodyHtml = $this->translator->trans('core.email.activate_account.body_html', $data);

        $this->mailer->raw($bodyText, function (Message $message) use ($user, $data, $bodyText, $bodyHtml) {
            $message->to($user->email);
            $message->subject($this->translator->trans('core.email.activate_account.subject'));
            $message->setBody($bodyText);
            $message->addPart($bodyHtml, 'text/html');
        });
    }

    /**
     * @param \Flarum\User\Event\EmailChangeRequested $event
     */
    public function whenUserEmailChangeWasRequested(EmailChangeRequested $event)
    {
        $email = $event->email;
        $data = $this->getEmailData($event->user, $email);

        $bodyText = $this->translator->trans('core.email.confirm_email.body_text', $data);
        $bodyHtml = $this->translator->trans('core.email.confirm_email.body_html', $data);

        $this->mailer->raw($bodyText, function (Message $message) use ($email, $data, $bodyText, $bodyHtml) {
            $message->to($email);
            $message->subject($this->translator->trans('core.email.confirm_email.subject'));
            $message->setBody($bodyText);
            $message->addPart($bodyHtml, 'text/html');
        });
    }

    /**
     * @param User $user
     * @param string $email
     * @return EmailToken
     */
    protected function generateToken(User $user, $email)
    {
        $token = EmailToken::generate($email, $user->id);
        $token->save();

        return $token;
    }

    /**
     * Get the data that should be made available to email templates.
     *
     * @param User $user
     * @param string $email
     * @return array
     */
    protected function getEmailData(User $user, $email)
    {
        $token = $this->generateToken($user, $email);

        return [
            '{username}' => $user->display_name,
            '{url}' => $this->url->to('forum')->route('confirmEmail', ['token' => $token->token]),
            '{forum}' => $this->settings->get('forum_title')
        ];
    }
}
