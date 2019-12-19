<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Api\Controller;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\AssertPermissionTrait;
use Flarum\User\EmailToken;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Zend\Diactoros\Response\EmptyResponse;

class SendConfirmationEmailController implements RequestHandlerInterface
{
    use AssertPermissionTrait;

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
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param \Flarum\Settings\SettingsRepositoryInterface $settings
     * @param Mailer $mailer
     * @param UrlGenerator $url
     * @param TranslatorInterface $translator
     */
    public function __construct(SettingsRepositoryInterface $settings, Mailer $mailer, UrlGenerator $url, TranslatorInterface $translator)
    {
        $this->settings = $settings;
        $this->mailer = $mailer;
        $this->url = $url;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = Arr::get($request->getQueryParams(), 'id');
        $actor = $request->getAttribute('actor');

        $this->assertRegistered($actor);

        if ($actor->id != $id || $actor->is_activated) {
            throw new PermissionDeniedException;
        }

        $token = EmailToken::generate($actor->email, $actor->id);
        $token->save();

        $data = [
            '{username}' => $actor->username,
            '{url}' => $this->url->to('forum')->route('confirmEmail', ['token' => $token->token]),
            '{forum}' => $this->settings->get('forum_title')
        ];

        $bodyText = $this->translator->trans('core.email.activate_account.body_text', $data);
        $bodyHtml = $this->translator->trans('core.email.activate_account.body_html', $data);

        $this->mailer->raw($bodyText, function (Message $message) use ($actor, $bodyText, $bodyHtml) {
            $message->to($actor->email);
            $message->subject($this->translator->trans('core.email.activate_account.subject'));
            $message->setBody($bodyText);
            $message->addPart($bodyHtml, 'text/html');
        });

        return new EmptyResponse;
    }
}
