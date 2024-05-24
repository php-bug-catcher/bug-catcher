<?php

namespace App\Controller\Admin;

use App\Entity\Client\Center\Center;
use App\Entity\Client\Client;
use App\Entity\Client\Order\Order;
use App\Entity\Client\Order\Request;
use App\Entity\Client\Product\Category;
use App\Entity\Client\Product\Product;
use App\Entity\Project;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController {

	public function __construct(
		#[Autowire("%env(APP_NAME)%")]
		private readonly string $appName
	) {}

	#[Route('/admin', name: '')]
	public function index(): Response {
		return $this->render("admin/dashboard.html.twig");
	}


	public function configureUserMenu(UserInterface $user): UserMenu {
		return parent::configureUserMenu($user)
			->addMenuItems([
				MenuItem::linkToRoute('Change password', 'fa fa-key', 'app_change_password'),
			]);
	}

	public function configureDashboard(): Dashboard {
		return Dashboard::new()
			->setTitle($this->appName);
	}

	public function configureCrud(): Crud {
		return parent::configureCrud()
			->setFormThemes([
				'@EasyAdmin/crud/form_theme.html.twig',
				'extension/form.html.twig',
			]);
	}


	public function configureMenuItems(): iterable {
		yield MenuItem::linkToRoute('Dashboard', 'fa fa-home', 'app.dashboard');
		yield MenuItem::linkToCrud('Users', 'fa-solid fa-user-tie', User::class);
		yield MenuItem::linkToCrud('Projects', 'fa-solid fa-shield-dog', Project::class);
	}

	public function configureActions(): Actions {
		return parent::configureActions()
			->add(Crud::PAGE_INDEX, Action::DETAIL)
			->update(Crud::PAGE_DETAIL, Action::EDIT, static function (Action $action) {
				return $action->setIcon('fa fa-edit');
			})
			->update(Crud::PAGE_DETAIL, Action::INDEX, static function (Action $action) {
				return $action->setIcon('fa fa-list');
			});
	}
}
