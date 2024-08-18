<?php
namespace {

	use BugCatcher\Controller\Admin\DashboardController as AdminDashboardController;
	use BugCatcher\Controller\DashboardController;
	use BugCatcher\Controller\HelloController;
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
			->add('bug_catcher.dashboard.status', '/status/{status}')
				->controller(DashboardController::class . "::index")
				->methods(['GET'])
			->add('bug_catcher.dashboard.detail', '/detail/{record}')
			->controller(DashboardController::class . "::detail")
				->methods(['GET']);
		$routes
			->add('bug_catcher.admin', '/admin')
			->controller(AdminDashboardController::class . "::index")
			->methods(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);
	};

}