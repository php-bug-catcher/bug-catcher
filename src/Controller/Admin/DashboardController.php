<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use PhpSentinel\BugCatcher\Entity\Client\Center\Center;
use PhpSentinel\BugCatcher\Entity\Client\Client;
use PhpSentinel\BugCatcher\Entity\Client\Order\Order;
use PhpSentinel\BugCatcher\Entity\Client\Order\Request;
use PhpSentinel\BugCatcher\Entity\Client\Product\Category;
use PhpSentinel\BugCatcher\Entity\Client\Product\Product;
use PhpSentinel\BugCatcher\Entity\Notifier;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController {

	public function __construct(
		#[Autowire("%env(APP_NAME)%")]
		private readonly string $appName
	) {}

	#[Route('/admin', name: 'admin')]
	public function index(): Response {
		return $this->render("@BugCatcher/admin/dashboard.html.twig");
	}



	public function configureUserMenu(UserInterface $user): UserMenu {
		return parent::configureUserMenu($user)
			->addMenuItems([
				MenuItem::linkToRoute('Change password', 'fa fa-key', 'bug_catcher.security.change-password'),
			]);
	}

	public function configureDashboard(): Dashboard {
		return Dashboard::new()
			->setTitle($this->appName);
	}

	public function configureCrud(): Crud {
		return parent::configureCrud()
			->overrideTemplate("layout", '@BugCatcher/admin/layout.html.twig')
			->setFormThemes([
				'@EasyAdmin/crud/form_theme.html.twig',
			]);
	}


	public function configureMenuItems(): iterable {
		$em               = $this->container->get(EntityManagerInterface::class);
		$classMetadata    = $em->getClassMetadata(Notifier::class);
		$discriminatorMap = $classMetadata->discriminatorMap;

		$notifiers = [];
		foreach ($discriminatorMap as $name => $class) {
			$notifiers[] = MenuItem::linkToCrud(ucfirst($name), 'fa-solid fa-satellite-dish', $class);;
		}

		yield MenuItem::linkToRoute('Dashboard', 'fa fa-home', 'bug_catcher.dashboard.index');
		yield MenuItem::linkToCrud('Users', 'fa-solid fa-user-tie', User::class);
		yield MenuItem::linkToCrud('Projects', 'fa-solid fa-shield-dog', Project::class);
		yield MenuItem::subMenu('Notifiers', 'fa-regular fa-bell')->setSubItems($notifiers);
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

	public static function getSubscribedServices(): array {
		$services                                = parent::getSubscribedServices();
		$services[EntityManagerInterface::class] = EntityManagerInterface::class;

		return $services;
	}
}
