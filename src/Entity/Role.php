<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 3. 2024
 * Time: 10:41
 */
namespace App\Entity;

enum Role: string {
	case ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
	case ROLE_ADMIN       = 'ROLE_ADMIN';
	case ROLE_CUSTOMER    = 'ROLE_CUSTOMER';
	case ROLE_USER        = 'ROLE_USER';

	public static function getGlobalRoles(): array {
		return [
			self::ROLE_ADMIN->value    => "Admin",
			self::ROLE_CUSTOMER->value => "Customer",
		];
	}

}
