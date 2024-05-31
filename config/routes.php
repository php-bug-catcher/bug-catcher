<?php
namespace {

	use PhpSentinel\BugCatcher\Controller\Admin\DashboardController as AdminDashboardController;
	use PhpSentinel\BugCatcher\Controller\DashboardController;
	use PhpSentinel\BugCatcher\Controller\HelloController;
	use PhpSentinel\BugCatcher\Controller\SecurityController;
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
			->add('bug_catcher.dashboard.status', '/status/{status}')
				->controller(DashboardController::class . "::index")
				->methods(['GET'])
			->add('bug_catcher.dashboard.detail', '/detail/{record}')
				->controller(DashboardController::class . "::index")
				->methods(['GET']);
		$routes
			->add('bug_catcher.admin', '/admin')
			->controller(AdminDashboardController::class . "::index")
			->methods(['GET','POST','PUT','DELETE']);
	};

}