## Custom notifier

You are free to create your own notifier. You can send email, SMS or whatever you want.

**Email Notifier**

```php
namespace App\EventSubscriber;

use PhpSentinel\BugCatcher\Entity\NotifierEmail;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class EmailNotifyListener {
	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierEmail){
			// send email
		}
	}
}
```

**Custom Notifier**

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;

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
cp vendor/php-sentinel/bug-catcher/config/doctrine/Notifier.orm.xml config/doctrine/BugCatcherBundle/Notifier.orm.xml
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

use PhpSentinel\BugCatcher\Entity\NotifierEmail;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
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