<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Mail\Job;

use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

class SendRawEmailJob extends AbstractJob
{
    private $email;
    private $subject;
    private $bodyText;
    private $bodyHtml;

    public function __construct(string $email, string $subject, string $bodyText, string $bodyHtml = null)
    {
        $this->email = $email;
        $this->subject = $subject;
        $this->bodyText = $bodyText;
        $this->bodyHtml = $bodyHtml;
    }

    public function handle(Mailer $mailer)
    {
        $mailer->raw($this->bodyText, function (Message $message) {
            $message->to($this->email);
            $message->subject($this->subject);
            if ($this->bodyHtml != null) {
                $message->setBody($this->bodyText);
                $message->addPart($this->bodyHtml, 'text/html');
            }
        });
    }
}
