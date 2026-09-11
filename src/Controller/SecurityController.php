<?php

namespace BugCatcher\Controller;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use BugCatcher\Form\ChangePasswordType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{

	public function __construct(
		private readonly string $logo,
	) {}

	/**
	 * The template owns the markup and the labels now, so this only passes data. The field
	 * names it used to hand over are Symfony's form_login defaults, which is exactly what the
	 * firewall is configured with; the template still accepts them as overrides.
	 */
	public function login(AuthenticationUtils $authenticationUtils, Packages $assetManager): Response {
		return $this->render('@BugCatcher/security/login.html.twig', [
			'error'                => $authenticationUtils->getLastAuthenticationError(),
			'last_username'        => $authenticationUtils->getLastUsername(),
			'logo_url'             => $assetManager->getUrl("/assets/logo/{$this->logo}/vertical.svg", 'bug_catcher'),
			'csrf_token_intention' => 'authenticate',
			'target_path'          => $this->generateUrl('bug_catcher.dashboard.index'),
			'remember_me_enabled'  => true,
			'remember_me_checked'  => true,
		]);
	}

	public function changePassword(
		Request                     $request,
		UserPasswordHasherInterface $userPasswordHasher,
		EntityManagerInterface      $entityManager,
		TranslatorInterface         $translator
	): Response {
		$form = $this->createForm(ChangePasswordType::class);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$user = $this->getUser();

			if ($userPasswordHasher->isPasswordValid($user, $form->get('oldPassword')->getData())) {
				$user->setPassword(
					$userPasswordHasher->hashPassword($user, $form->get('newPassword')->getData())
				);
				$entityManager->persist($user);
				$entityManager->flush();
				$this->addFlash('success', $translator->trans('Password changed'));

				return $this->redirectToRoute('bug_catcher.dashboard.index');
			}

			$form->get('oldPassword')->addError(new FormError($translator->trans('Old password is not valid')));
		}

		// single exit for both the first visit and a rejected old password
		return $this->render('@BugCatcher/security/change_password.html.twig', [
			'form' => $form->createView(),
		]);
	}

	public function logout(): void {
		throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
	}
}
