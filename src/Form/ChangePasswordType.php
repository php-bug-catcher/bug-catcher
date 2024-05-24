<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 22. 3. 2024
 * Time: 20:10
 */
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ChangePasswordType extends AbstractType {
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		$builder
			->add("oldPassword", PasswordType::class, [
				'label' => "Old password",
				'attr'  => [
					'placeholder' => 'Old password',
				],
			])
			->add("newPassword", RepeatedType::class, [
				'type'           => PasswordType::class,
				'first_options'  => [
					'label' => "New password",
					'attr'  => [
						'placeholder' => 'New password',
					],
				],
				'second_options' => [
					'label' => "Repeat new password",
					'attr'  => [
						'placeholder' => 'Repeat new password',
					],
				],
			])
			->add("submit", SubmitType::class, [
				'label' => "Change password",
			]);
	}


}