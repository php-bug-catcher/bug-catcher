<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController {

	public function __construct(
		#[Autowire("%env(APP_NAME)%")]
		private string $appName
	) {}

	#[Route(path: '/login', name: 'app_login')]
	public function login(AuthenticationUtils $authenticationUtils, Packages $assetManager): Response {
		// get the login error if there is one
		$error = $authenticationUtils->getLastAuthenticationError();

		// last username entered by the user
		$lastUsername = $authenticationUtils->getLastUsername();
		$logoUrl      = $assetManager->getUrl("images/logo.svg");

		return $this->render('security/login.html.twig', [
			'error'                   => $error,
			'last_username'           => $lastUsername,
			'favicon_path'            => '/favicon-admin.svg',
			'page_title'              => <<<HTML
<div style="text-align: center;">
<img src="$logoUrl"/>
<h2>{$this->appName}</h2>
</div>
HTML,
			'csrf_token_intention'    => 'authenticate',
			'target_path'             => $this->generateUrl('admin'),
			'username_label'          => 'Your email address',
			'password_label'          => 'Your password',
			'sign_in_label'           => 'Log in',
			'username_parameter'      => '_username',
			'password_parameter'      => '_password',
			'forgot_password_enabled' => false,
			'forgot_password_label'   => 'Forgot your password?',
			'remember_me_enabled'     => true,
			'remember_me_parameter'   => 'custom_remember_me_param',
			'remember_me_checked'     => true,
			'remember_me_label'       => 'Remember me',
		]);
	}

	#[Route(path: '/change-password', name: 'app_change_password')]
	public function changePassword(
		Request                     $request,
		UserPasswordHasherInterface $userPasswordHasher,
		EntityManagerInterface      $entityManager,
		TranslatorInterface         $translator
	) {

		$form = $this->createForm(ChangePasswordType::class);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$oldPassword = $form->get('oldPassword')->getData();
			if (!$userPasswordHasher->isPasswordValid($this->getUser(), $oldPassword)) {
				$form->get('oldPassword')->addError(new FormError($translator->trans('Old password is not valid')));

				return $this->render('security/change_password.html.twig', [
					'form' => $form->createView(),
				]);
			}
			$user = $this->getUser();
			$user->setPassword(
				$userPasswordHasher->hashPassword(
					$user,
					$form->get('newPassword')->getData()
				)
			);
			$entityManager->persist($user);
			$entityManager->flush();
			$this->addFlash('success', 'Password changed');

			return $this->redirectToRoute('admin');
		}

		return $this->render('security/change_password.html.twig', [
			'form' => $form->createView(),
		]);
	}

	#[Route(path: '/logout', name: 'app_logout')]
	public function logout(): void {
		throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
	}
}
