<?php

namespace Survos\ApiGrid\Command;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\EntityManagerInterface;
use Meilisearch\Endpoints\Indexes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\IO;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use ApiPlatform\Metadata\ApiFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use ApiPlatform\Metadata\ApiResource;

#[AsCommand('api:index', 'Create a meili index for a doctrine entity', aliases: ['app:index-db'])]
final class ApiIndexCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes;
    use RunsCommands;
    use RunsProcesses;

    public function __construct(
        protected ParameterBagInterface $bag,
        protected EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
//        private NormalizerInterface $normalizer,
    ) {
        parent::__construct();
    }

    public function __invoke(
        IO $io,
        // custom injections
        // UserRepository $repo,
        // expand the arguments and options
        #[Option(description: 'the maximum number of entities to index')] int $limit = 0,
        #[Option(name: 'batch', description: 'the batch size for submitting to meili, default:500')] int $batchSize = 500,
        #[Option(name: 'min', description: 'minimum number of meili objects')] int $minMeiliCount = 0,
        #[Option(description: 'delete the index first')] ?bool $reset = null,
    ): void {

        $entityClasses = [];
        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($metas as $meta) {
            foreach ($meta->getReflectionClass()->getAttributes(ApiResource::class) as $attribute) {
                dd($attribute);

            }
        }

                foreach ($entityClasses as $class) {
            $indexName = (new \ReflectionClass($class))->getShortName();
            if ($reset) {
                $this->reset($indexName);
            }
            $index = $this->getIndex($indexName, Instance::DB_CODE_FIELD);
//            $task = $this->waitForTask($this->getMeiliClient()->createIndex($indexName, ['primaryKey' => Instance::DB_CODE_FIELD]));

            $this->configureSettings($class, $index);

            $stats = $this->indexClass($class, $index, $batchSize, $ownerCode, $ownerId, $subdomain, $minMeiliCount);
            $io->success($indexName . ' Document count:' . $stats['numberOfDocuments']);
        }
        $this->waitUntilFinished($index);
        $io->success('app:index-entity ' . $class . ' success.');
    }

    private function configureSettings(string $class, Indexes $index)
    {

        $reflection = new \ReflectionClass($class);
        $classAttributes = $reflection->getAttributes();
        $filterAttributes = [];
        $sortableAttributes = [];
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() == ApiFilter::class) {
                if ($attribute->getArguments()[0] == MultiFieldSearchFilter::class) {
                    $filterAttributes = $attribute->getArguments()['properties'];
                }
                if ($attribute->getArguments()[0] == OrderFilter::class) {
                    $sortableAttributes = $attribute->getArguments()['properties'];
                }
            }
        }

        $index->updateFilterableAttributes($filterAttributes);
        $index->updateSortableAttributes($sortableAttributes);
//        $index->updateSettings(); // could do this in one call
//        $stats = $this->waitUntilFinished($index, $this->io());
    }

    private function indexClass(
        string $class,
        Indexes $index,
        int $batchSize,
        ?string $ownerCode = null,
        ?string $ownerID = null,
        ?string $subdomain = null,
        ?int $minMeiliCount = 0,
    ): array {

        $startingAt = 0;
        $records = [];
        $repo =  $this->entityManager->getRepository($class);
        $qb = $repo->createQueryBuilder('r');
        $count = null;
        if ($ownerID) {
            $owner = $this->ownerRepository->findOneBy(['institutionId' => $ownerID]);
            $ownerCode = $owner->getCode();
        }
        if ($ownerCode) {
            $qb = ($class == Project::class)
                ? $qb->join('r.owner', 'owner')->andWhere('owner.code = :code')->setParameter('code', $ownerCode)
                : $qb->andWhere('r.code = :code')->setParameter('code', $ownerCode)
                ;
            if (($class == Project::class) && $subdomain) {
                $qb->andWhere('r.subdomain = :projectCode')->setParameter('projectCode', $subdomain);
            }
//            $results = $qb->getQuery()->getResult();
//            $count = count($results);
        } else {
            $qb = $this->entityManager->getRepository($class)->createQueryBuilder('e');
            if ($class == Project::class) {
                if ($subdomain) {
                    if (is_numeric($subdomain)) {
                        $qb->andWhere('e.idInSource = :code')->setParameter('code', (int)$subdomain);
                    } else {
                        $qb->andWhere('e.subdomain = :code')->setParameter('code', $subdomain);
                    }
                } else {
                    $count = $this->entityManager->createQuery(sprintf('select count(r) from %s r', $class))->getSingleScalarResult();
                }
            }
        }
        $results = $qb->getQuery()->toIterable();
        if (is_null($count)) {
            // slow if not filtered!
            $count = count(iterator_to_array($results, false));
        }
        $results = $qb->getQuery()->toIterable();
        $this->io()->title("$count $class");
        if (!$count) {
            return ['numberOfDocuments' => 0];
        }
        if ($subdomain) {
            assert($count == 1, "$count should be one for " . $subdomain);
        }
        $progressBar = $this->getProcessBar($count);
        $progressBar->setMessage("Indexing $class");

        foreach ($results as $idx => $r) {
            // @todo: pass in groups?  Or configure within the class itself?
            // maybe these should come from the ApiPlatform normalizer.
            $data = $this->normalizer->normalize($r, null, ['groups' => ['rp', 'project.read', 'owner.read', 'marking', 'translation']]);
            $data['id'] = $data['code'];
            if (array_key_exists('keyedTranslations', $data)) {
                $data['_translations'] = $data['keyedTranslations'];
                $data['targetLocales'] = array_keys($data['_translations']);
//                unset($data['keyedTranslations']);
            }
//            dd($r, $data);
            assert(array_key_exists('_translations', $data), "Missing translations for " . $r::class);
            // if live, only add if indexed and meiliCount
//            dd(array_keys($data), $data['keyedTranslations']);
            // total hack, this doesn't belong in the indexer, but in the deserializer before sending the results back,
            // so somewhere in meili?
//            assert($data['locale'], "Missing locale for $class " . $code);
            if ($projectLocale = $data['projectLocale'] ?? $data['locale'] ?? false) {
                $language = Languages::getName($projectLocale);
                $data['language'] = $language;
            }

            if (($class == Project::class) && $minMeiliCount && ($data['meiliObjectCount'] < $minMeiliCount)) {
                continue;
            }
            $records[] = $data;

            if (( ($progress = $progressBar->getProgress()) % $batchSize) === 0) {
                $task = $index->addDocuments($records, Instance::DB_CODE_FIELD);
                if (!$progress) {
                    $this->waitForTask($task);
                }
                $records = [];
            }
            $progressBar->advance();
        }
        if (count($records)) {
            $task = $index->addDocuments($records, Instance::DB_CODE_FIELD);
            // if debugging
//            $this->waitForTask($task);
        }

        $progressBar->finish();

        $this->showIndexSettings($index);

        return $this->waitUntilFinished($index);
    }

    private function getTranslationArray($entity, $accessor)
    {
        $rows = [];
        $updatedRow = [Instance::DB_CODE_FIELD => $entity->getCode()];
        foreach ($entity->getTranslations() as $translation) {
            foreach (Instance::TRANSLATABLE_FIELDS as $fieldName) {
                $translatedValue = $accessor->getValue($translation, $fieldName);
                $updatedRow['_translations'][$translation->getLocale()][$fieldName] = $translatedValue;
            }
        }

        return $updatedRow;
    }

    // @todo: move to trait or helper
    private function getProcessBar(int $total=0): ProgressBar
    {
        // https://jonczyk.me/2017/09/20/make-cool-progressbar-symfony-command/
        $progressBar = new ProgressBar($this->io(), $total);
        if ($total) {
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        } else {
            $progressBar->setFormat(' %current% [%bar%] %elapsed:6s% %memory:6s% -- %message%');

        }
        return $progressBar;
    }

}
