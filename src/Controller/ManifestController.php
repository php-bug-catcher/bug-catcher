<?php

namespace BugCatcher\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The web app manifest, which exists for one reason: Chrome allows audio to autoplay with sound on
 * a site the user has installed as an app, and nowhere else without a gesture in the current
 * document. Without it, warning-sound_controller's probe is refused on every single page load and
 * the dashboard asks to be clicked before it may make a noise - which is no way to run a wall
 * monitor. The grant only covers pages inside `scope`.
 *
 * @link https://developer.chrome.com/blog/autoplay
 */
final class ManifestController extends AbstractController
{

	public function __construct(
		private readonly string $appName,
		private readonly string $logo,
	) {}

	public function index(Packages $assetManager): JsonResponse {
		// generated, not '/': the recipe mounts the bundle's routes at the root, but an
		// application may not, and an installed app that autoplays outside its scope does not exist
		$start = $this->generateUrl('bug_catcher.dashboard.index');
		$icon  = fn(string $file): string => $assetManager->getUrl("/assets/logo/{$this->logo}/$file", 'bug_catcher');

		$response = new JsonResponse([
			'id'         => $start,
			'name'       => $this->appName,
			'short_name' => $this->appName,
			'start_url'  => $start,
			'scope'      => $start,
			'display'    => 'standalone',
			// the one place in this bundle where a colour is not a --bc-* token: the OS paints the
			// window frame and the splash screen before a stylesheet exists, so it cannot resolve
			// one. These are the dark theme's --bc-bg and --bc-surface (assets/styles/app.css).
			'background_color' => '#04050d',
			'theme_color'      => '#0a0e1f',
			// Chromium refuses to install a manifest without both a 192 and a 512 PNG, so neither
			// of these is decorative. The SVG is the one browsers that take it actually use.
			'icons' => [
				['src' => $icon('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
				['src' => $icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
				['src' => $icon('icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
			],
		]);
		$response->headers->set('Content-Type', 'application/manifest+json');
		// a manifest is read by hand in DevTools far more often than by a program; \/bundles\/...
		// is valid JSON and unreadable
		$response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_SLASHES);

		return $response;
	}
}
