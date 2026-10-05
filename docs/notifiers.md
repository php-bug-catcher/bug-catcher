## Making the sound notifier audible for good

Browsers refuse to play audio a page did not get a user gesture for, so by default the dashboard
asks to be clicked once per page load before `NotifierSound` may make a noise - which is no way to
run a wall monitor.

The dashboard ships a web app manifest (`/manifest.webmanifest`) for exactly this reason. **Install
it as an app** - in Chrome, ⋮ → *Cast, save and share* → *Install page as app* - and the autoplay
block is lifted for every page in the app's scope, permanently and across restarts. Installing
requires HTTPS (or `localhost`), and the menu entry only appears once you are logged in.

If the installation is a screen nobody logs into, start the browser with the policy switched off
instead:

```bash
chromium --kiosk --autoplay-policy=no-user-gesture-required https://bugcatcher.example.com/
```

Fleet-wide, the same thing is Chrome's `AutoplayAllowlist` enterprise policy. Firefox and Edge need
none of this: both have a per-site *autoplay* permission in the padlock menu that you set to *Allow*
once.

## What a notifier counts

A notifier has a `component`, chosen in the admin from `bug_catcher.notifier_components`. It decides
*what is counted* against the notifier's threshold - separately from *how it tells you*, which is the
custom notifier below. Three are built in:

```yaml
bug_catcher:
    notifier_components:
        "Project error count": project-error-count
        "Same error count": same-error-count
        "Performance regression count": perf-regression-count
        "Deploys that failed": my-component        # your own
```

| component | counts |
|---|---|
| `project-error-count` | every unresolved `Record` of the project, one count per project |
| `same-error-count` | the same, grouped by `hash` as well, so twenty copies of one error weigh more than twenty different ones |
| `perf-regression-count` | only `RecordPerformance` - routes whose performance regressed |

The first two are rooted at the `Record` hierarchy with **no discriminator filter**, so a performance
regression already raises them. That is deliberate and unchanged: a regression is a record somebody
has to look at. `perf-regression-count` exists because it is the only way to be told about
regressions *alone*, at a threshold chosen for regressions - twenty exceptions and twenty routes
that got slower are not the same news, and one threshold cannot serve both.

### A component of your own

The built-in switch in `BugCatcher\EventSubscriber\NotifyCalculateListener` is closed, so a new
component is a name in the config plus a listener on `NotifyCalculateEvent` that recognises it:

```php
use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Enum\Importance;
use BugCatcher\Event\NotifyCalculateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class MyCalculateListener
{
    public function __invoke(NotifyCalculateEvent $event): void
    {
        if ($event->notifier->getComponent() !== 'my-component') {
            return;
        }

        foreach ($event->notifier->getProjects() as $project) {
            if (!$project->isEnabled()) {
                continue;
            }

            $count = /* whatever you count */;
            if ($count === 0) {
                // no status at all rather than a status of zero: a notifier has nothing to clear
                continue;
            }

            $status = new NotifierStatus($project);
            $event->addStatus($status);
            $status->incrementImportance(Importance::High, $count, $event->notifier->getThreshold());
        }
    }
}
```

The `Importance` you pass is the *group* the count is added to, not the level that comes out:
`NotifierStatus` scales the count against the threshold, so a count well over it saturates at
`Importance::MamaMia`. Whatever comes out is compared against the notifier's `minimalImportance`
before `NotifyEvent` is dispatched.

Do not add a result cache to whatever you query here. `NotifyCalculateEvent` is dispatched
immediately after a record is written - once per record, in a loop, during `app:perf:detect` - so a
cached count is a count from before the write, and a notifier can fail to cross its threshold on the
very run that gave it something to cross it with.

## Custom notifier

You are free to create your own notifier. You can send email, SMS or whatever you want.

**Email Notifier**

```php
namespace App\EventSubscriber;

use BugCatcher\Entity\NotifierEmail;
use BugCatcher\Event\NotifyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;

#[AsEventListener]
class EmailNotifyListener {
	
	public function __construct(
		private readonly MailerInterface $mailer
	) {}

	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierEmail) {
			if ($event->importance->isHigherOrEqualThan($event->notifier->getMinimalImportance())) {
			    foreach($event->project->getUsers() as $user){
                    $email = (new Email())
                        ->from('hello@example.com')
                        ->to($user->getEmail())
                        ->subject("Threshold reached {$event->project->getName()}")
                        ->text('Hey, the threshold was reached!')
                        ->html('<p>Hey, the threshold was reached!</p>');
    
                    $this->mailer->send($email);
				}
			}
		}
	}

}
```

**Custom Notifier**

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use BugCatcher\Repository\NotifierRepository;

class NotifierSms extends Notifier {

    #[ORM\Column(type: 'string')]
	private string $telephoneNumber;

	public function getTelephoneNumber(): string {
		return $this->telephoneNumber;
	}

	public function setTelephoneNumber(string $number): self {
		$this->telephoneNumber = $number;

		return $this;
	}

}
```

See [how to oweride entity](./custom_record.md#copy-orm-files) for more details.

```
cp vendor/php-bug-catcher/bug-catcher/config/doctrine/Notifier.orm.xml config/doctrine/BugCatcherBundle/Notifier.orm.xml
```

```xml
<!--config/doctrine/BugCatcherBudnle/Notifier.orm.xml-->
<!--...-->
<discriminator-map>
    <!--...-->
    <discriminator-mapping value="sms-notifier" class="App\Entity\NotifierSms"/>
</discriminator-map>
<!--...-->
```

```php
namespace App\EventSubscriber;

use BugCatcher\Entity\NotifierEmail;
use BugCatcher\Event\NotifyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class SmsNotifyListener {
	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierSms){
			// send sms
		}
	}
}
```