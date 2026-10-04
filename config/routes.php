<?php
namespace {

	use BugCatcher\Controller\Admin\DashboardController as AdminDashboardController;
	use BugCatcher\Controller\DashboardController;
	use BugCatcher\Controller\HelloController;
	use BugCatcher\Controller\ManifestController;
	use BugCatcher\Controller\PerformanceController;
	use BugCatcher\Controller\RecordStatusController;
	use BugCatcher\Controller\SecurityController;
	use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

	/**
	 * @link https://symfony.com/doc/current/bundles/best_practices.html#routing
	 */
	return static function (RoutingConfigurator $routes): void {
		$routes
			->add('bug_catcher.security.login', '/login')
				->controller(SecurityController::class . "::login")
				->methods(['GET', 'POST'])
			->add('bug_catcher.security.change-password', '/change-password')
				->controller(SecurityController::class . "::changePassword")
				->methods(['GET', 'POST'])
			->add('bug_catcher.security.logout', '/logout')
				->controller(SecurityController::class . "::logout")
			->methods(['GET']);
		$routes
			->add('bug_catcher.dashboard.index', '/')
				->controller(DashboardController::class . "::index")
				->methods(['GET'])
			->add('bug_catcher.dashboard.detail', '/detail/{record}')
			->controller(DashboardController::class . "::detail")
				->methods(['GET'])
			->add('bug_catcher.dashboard.record-status', '/detail/{record}/status/{status}')
				->controller(RecordStatusController::class . "::changeStatus")
				->methods(['POST'])
				// setStatus() interpolates the new status straight into DQL, keep it to known values
				->requirements(['status' => 'resolved|archived'])
			// .webmanifest rather than manifest.json: Encore's own asset manifest is already
			// called manifest.json and is published at /bundles/bugcatcher/manifest.json
			->add('bug_catcher.manifest', '/manifest.webmanifest')
				->controller(ManifestController::class . "::index")
				->methods(['GET']);
		// two routes onto one action rather than an optional placeholder: "/performance/" with a
		// trailing nothing is not a URL anybody should be able to generate, and the all-projects
		// page wants a name of its own for the nav to link to
		$routes
			->add('bug_catcher.performance.index', '/performance')
				->controller(PerformanceController::class . "::index")
				->methods(['GET'])
			->add('bug_catcher.performance.project', '/performance/{project}')
				->controller(PerformanceController::class . "::index")
				->methods(['GET']);
		$routes
			->add('bug_catcher.admin', '/admin')
			->controller(AdminDashboardController::class . "::index")
			->methods(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);
	};

}