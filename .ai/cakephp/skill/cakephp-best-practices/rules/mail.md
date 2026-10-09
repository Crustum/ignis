# Mail Best Practices

Book: [Mailer](https://book.cakephp.org/5/en/core-libraries/email.html)

## Configure Profiles and Transports in Config

Define `Email` / `EmailTransport` settings in application config, then construct `Mailer` with a profile name. Avoid scattering host/credentials in call sites.

```php
use Cake\Mailer\Mailer;

$mailer = new Mailer('default');
$mailer
    ->setTo('you@example.com')
    ->setSubject('About')
    ->deliver('My message');
```

## Prefer Templated Mail Over Inline HTML Strings

Use `viewBuilder()` templates under the mailer templates path so HTML/text stay maintainable.

```php
$mailer = new Mailer('default');
$mailer
    ->setTo($user->email)
    ->setSubject('Welcome')
    ->setEmailFormat('both')
    ->setViewVars(['user' => $user])
    ->viewBuilder()
        ->setTemplate('welcome')
        ->setLayout('default');

$mailer->deliver();
```

## Create Reusable Mailer Classes

Put related messages on a dedicated Mailer subclass with named methods instead of duplicating setup everywhere.

```php
namespace App\Mailer;

use Cake\Mailer\Mailer;

class UserMailer extends Mailer
{
    public function welcome($user): void
    {
        $this
            ->setTo($user->email)
            ->setSubject(sprintf('Welcome %s', $user->name))
            ->viewBuilder()
                ->setTemplate('welcome_mail');
    }

    public function resetPassword($user): void
    {
        $this
            ->setTo($user->email)
            ->setSubject('Reset password')
            ->setViewVars(['token' => $user->token]);
    }
}
```

Send from a controller with `MailerAwareTrait`:

```php
use Cake\Mailer\MailerAwareTrait;

class UsersController extends AppController
{
    use MailerAwareTrait;

    public function register(): void
    {
        $user = $this->Users->newEmptyEntity();
        if ($this->getRequest()->is('post')) {
            $user = $this->Users->patchEntity($user, $this->getRequest()->getData());
            if ($this->Users->save($user)) {
                $this->getMailer('User')->send('welcome', [$user]);
            }
        }
        $this->set(['user' => $user]);
    }
}
```

Or subscribe the mailer to `Model.afterSave` via `implementedEvents()` so controllers stay free of mail setup (see Mailer book “Creating Reusable Emails”).

## Queue Slow Mail, Keep Urgent Mail Synchronous

Queue mail that calls out to delivery services when it does not need to complete before the response: add `Cake\Queue\Mailer\QueueTrait` to the mailer and `push()` the action, or route the profile through `QueueTransport` (see the `queue-development` skill). Keep mail synchronous when the caller must know immediately whether delivery was accepted, or when no queue worker is available.

## Keep Content and Delivery Tests Separate

- Render/content: exercise templates or Mailer methods that build messages.
- Delivery: assert queued mail on the queue (`QueueTrait` asserts) and synchronous mail on delivery, so failures identify the affected behavior — never mix the two in one test.

See the `testing-best-practices` skill for PHPUnit patterns.
