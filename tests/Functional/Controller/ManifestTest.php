<?php

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;

/**
 * The manifest is not decoration: Chrome only lifts the autoplay block for a site the user has
 * installed as an app, and it only installs one whose manifest it can parse and that carries both
 * icon sizes. Everything asserted here is a condition of that, so none of it is cosmetic.
 */
class ManifestTest extends KernelTestCase
{
	use apiTestHelper;

	public function testManifestIsInstallable(): void {
		$user = UserFactory::createOne();
		$this->loginUser($user->_real());
		[$browser] = $this->browser([]);

		$browser
			->get('/manifest.webmanifest')
			->assertStatus(200)
			->assertContains('"display":"standalone"');

		$manifest = json_decode($browser->client()->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

		$this->assertSame('application/manifest+json', $browser->client()->getResponse()->headers->get('Content-Type'));
		$this->assertSame('/', $manifest['start_url']);
		// the installed-app autoplay grant only covers pages inside the scope
		$this->assertSame('/', $manifest['scope']);
		$this->assertNotEmpty($manifest['name']);

		$sizes = array_column($manifest['icons'], 'sizes');
		$this->assertContains('192x192', $sizes, 'Chromium will not install a manifest without a 192px icon');
		$this->assertContains('512x512', $sizes, 'Chromium will not install a manifest without a 512px icon');
	}

	public function testTheDashboardLinksTheManifest(): void {
		$project = ProjectFactory::createOne(['code' => 'manifest', 'enabled' => true])->_real();
		$user    = UserFactory::createOne([
			'email'    => 'manifest@example.com',
			'enabled'  => true,
			'roles'    => ['ROLE_DEVELOPER'],
			'projects' => [$project],
		])->_real();

		[$browser] = $this->browser();

		$browser
			->actingAs($user)
			->visit('/')
			->assertStatus(200)
			->assertContains('rel="manifest"');
	}

	/**
	 * The manifest route sits behind the application's catch-all access rule, so linking it to an
	 * anonymous visitor would only earn a redirect to /login and a manifest the browser cannot
	 * parse. Nobody installs the app from the login page anyway.
	 */
	public function testTheLoginPageDoesNotLinkTheManifest(): void {
		[$browser] = $this->browser();

		$browser
			->visit('/login')
			->assertStatus(200)
			->assertNotContains('rel="manifest"');
	}
}
