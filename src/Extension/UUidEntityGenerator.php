<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 6. 9. 2023
 * Time: 16:07
 */
namespace App\Extension;

use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Uid\Uuid;

#[AsDecorator(decorates: 'maker.generator')]
#[When(env: 'dev')]
class UUidEntityGenerator extends Generator {


	/**
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(
		#[AutowireDecorated] private readonly Generator $generator
	) {}

	public static function getControllerBaseClass(): ClassNameDetails {
		return new ClassNameDetails(AbstractController::class, '\\');
	}

	/**
	 * @param array<string,mixed> $variables
	 * @throws Exception
	 */
	public function generateClass(string $className, string $templateName, array $variables = []): string {
		/** @var UseStatementGenerator $useGenerator */
		$useGenerator = $variables['use_statements']??null;
		if ($templateName === 'doctrine/Entity.tpl.php') {
			$templateName = __DIR__ . '/../../templates/extension/maker/Entity.tpl.php';
			$useGenerator->addUseStatement(Uuid::class);
			$useGenerator->addUseStatement(UuidType::class);
		}
		if ($templateName === 'doctrine/Repository.tpl.php') {
			$templateName = __DIR__ . '/../../templates/extension/maker/Repository.tpl.php';
			$variables['include_example_comments'] = false;
			$useGenerator->addUseStatement(QueryBuilder::class);
		}

		return $this->generator->generateClass($className, $templateName, $variables);
	}

	/**
	 * @param array<string,mixed> $variables
	 */
	public function generateFile(string $targetPath, string $templateName, array $variables = []): void {
		$this->generator->generateFile($targetPath, $templateName, $variables);
	}

	public function dumpFile(string $targetPath, string $contents): void {
		$this->generator->dumpFile($targetPath, $contents);
	}

	public function getFileContentsForPendingOperation(string $targetPath): string {
		return $this->generator->getFileContentsForPendingOperation($targetPath);
	}

	public function createClassNameDetails(
		string $name, string $namespacePrefix, string $suffix = '', string $validationErrorMessage = ''): ClassNameDetails {
		return $this->generator->createClassNameDetails($name, $namespacePrefix, $suffix,
			$validationErrorMessage);
	}

	public function getRootDirectory(): string {
		return $this->generator->getRootDirectory();
	}

	public function hasPendingOperations(): bool {
		return $this->generator->hasPendingOperations();
	}

	public function writeChanges() {
		$this->generator->writeChanges();
	}

	public function getRootNamespace(): string {
		return $this->generator->getRootNamespace();
	}

	/**
	 * @param string[] $parameters
	 */
	public function generateController(string $controllerClassName, string $controllerTemplatePath, array $parameters = []): string {
		return $this->generator->generateController($controllerClassName, $controllerTemplatePath,
			$parameters);
	}

	/**
	 * @param array<string,mixed> $variables
	 */
	public function generateTemplate(string $targetPath, string $templateName, array $variables = []): void {
		$this->generator->generateTemplate($targetPath, $templateName, $variables);
	}

	/**
	 * @return string[]
	 */
	public function getGeneratedFiles(): array {
		return $this->generator->getGeneratedFiles();
	}


}