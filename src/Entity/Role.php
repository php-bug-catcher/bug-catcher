<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 3. 2024
 * Time: 10:41
 */
namespace App\Entity;

enum Role: string {
	case ROLE_ADMIN     = 'ROLE_ADMIN';
	case ROLE_DEVELOPER = 'ROLE_DEVELOPER';
	case ROLE_CUSTOMER  = 'ROLE_CUSTOMER';
	case ROLE_USER      = 'ROLE_USER';
	case RIGHT_NO_MENU = 'RIGHT_NO_MENU';

	public static function getGlobalRoles(): array {
		return [
			self::ROLE_DEVELOPER->value => "Developer",
			self::ROLE_CUSTOMER->value  => "Customer",
		];
	}

}
