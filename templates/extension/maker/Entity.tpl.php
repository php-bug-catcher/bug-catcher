<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

#[ORM\Entity(repositoryClass: <?= $repository_class_name ?>::class)]
<?php if ($should_escape_table_name): ?>#[ORM\Table(name: '`<?= $table_name ?>`')]
<?php endif ?>
<?php if ($api_resource): ?>
#[ApiResource]
<?php endif ?>
<?php if ($broadcast): ?>
#[Broadcast]
<?php endif ?>
class <?= $class_name ?> {

	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	private ?Uuid $id = null;

    public function getId(): ?Uuid{
        return $this->id;
    }
}
