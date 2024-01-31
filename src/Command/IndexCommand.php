<?php

namespace Survos\ApiGrid\Command;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\EntityManagerInterface;
use Meilisearch\Endpoints\Indexes;
use Psr\Log\LoggerInterface;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Service\DatatableService;
use Survos\ApiGrid\Service\MeiliService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'grid:index',
    description: 'Index entities for use with api-grid',
)]
class IndexCommand extends Command
{
    private SymfonyStyle $io;
    public function __construct(
        protected ParameterBagInterface $bag,
        protected EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private MeiliService $meiliService,
        private DatatableService $datatableService,
        private NormalizerInterface $normalizer,

    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('class', InputArgument::OPTIONAL, 'Class to index', null)
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset the indexes')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size to meili', 100)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'limit', 0)
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'filter in yaml format')
            ->addOption('dump', null, InputOption::VALUE_REQUIRED, 'dump the nth item', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $filter = $input->getOption('filter');
        $filterArray = $filter ? Yaml::parse($filter) : null;
        $class = $input->getArgument('class');
            $classes = [];
            // https://abendstille.at/blog/?p=163
            $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
            foreach ($metas as $meta) {
                foreach ($meta->getReflectionClass()->getAttributes(ApiResource::class) as $attribute) {
                    $args = $attribute->getArguments();
                    if (array_key_exists('normalizationContext', $args)) {
                        $groups = $args['normalizationContext']['groups'];
                        if (is_string($groups)) {
                            $groups = [$groups];
                        }
                        if ($class && ($meta->getName() <> $class)) {
                            continue;
                        }
                        $classes[$meta->getName()] = $groups;
                    }
                }
            }

        $this->io = new SymfonyStyle($input, $output);

        foreach ($classes as $class=>$groups) {
            $indexName = (new \ReflectionClass($class))->getShortName();
            if ($reset=$input->getOption('reset')) {
                $this->meiliService->reset($indexName);
            }
//            $task = $this->waitForTask($this->getMeiliClient()->createIndex($indexName, ['primaryKey' => Instance::DB_CODE_FIELD]));

            $index = $this->configureIndex($class, $indexName);
            $batchSize = $input->getOption('batch-size');



            $stats = $this->indexClass($class, $index, $batchSize, $indexName, $groups,
                $input->getOption('limit'),
                $input->getOption('filter') ? $filterArray: null,
                $input->getOption('dump'),
            );
            $this->io->success($indexName . ' Document count:' .$stats['numberOfDocuments']);
            $this->meiliService->waitUntilFinished($index);

            if ($this->io->isVerbose()) {
                $stats = $index->stats();
                // now what?

            }
            $this->io->success('app:index-entity ' . $class . ' success.');

        }

        return self::SUCCESS;

    }

    private function configureIndex(string $class, string $indexName): Indexes
    {

//        $reflection = new \ReflectionClass($class);
//        $classAttributes = $reflection->getAttributes();
//        $filterAttributes = [];
//        $sortableAttributes = [];
        $settings = $this->datatableService->getSettingsFromAttributes($class);
        $primaryKey = 'id'; // default, check for is_primary));
        $idFields = $this->datatableService->getFieldsWithAttribute($settings, 'is_primary');
        if (count($idFields)) $primaryKey = $idFields[0];
//        dd($settings, $filterAttributes);
//
//        foreach ($settings as $fieldname=>$classAttributes) {
//            if ($classAttributes['browsable']) {
//                $filterAttributes[] = $fieldname;
//            }
//            if ($classAttributes['sortable']) {
//                $sortableAttributes[] = $fieldname;
//            }
//            if ($classAttributes['searchable']) {
////                $searchAttributes[] = $fieldname;
//            }
//            if ($classAttributes['is_primary']??null) {
//                $primaryKey = $fieldname;
//            }
//        }

        $index = $this->meiliService->getIndex($indexName, $primaryKey);
//        $index->updateSortableAttributes($this->datatableService->getFieldsWithAttribute($settings, 'sortable'));
//        $index->updateSettings(); // could do this in one call

            $results = $index->updateSettings($settings = [
                'displayedAttributes' => ['*'],
                'filterableAttributes' => $this->datatableService->getFieldsWithAttribute($settings, 'browsable'),
                'sortableAttributes' => $this->datatableService->getFieldsWithAttribute($settings, 'sortable'),
                "faceting" => [
                    "sortFacetValuesBy" => ["*" => "count"],
                    "maxValuesPerFacet" => $this->meiliService->getConfig()['maxValuesPerFacet']
                ],
            ]);

            $stats = $this->meiliService->waitUntilFinished($index);
        return $index;
    }

    private function indexClass(string  $class, Indexes $index, int $batchSize, ?string $indexName=null,
                                array $groups=[],
                                int $limit=0,
                                ?array $filter=[],
                                int $dump=0,
                                ?string $subdomain=null,
    ): array
    {

        $startingAt = 0;
        $records = [];
        $primaryKey = $index->getPrimaryKey();
        $repo =  $this->entityManager->getRepository($class);
        $qb = $repo->createQueryBuilder('r');
        $count = null;
        $qb = $this->entityManager->getRepository($class)->createQueryBuilder('e');
        if ($filter) {
            foreach ($filter as $var => $val) {
                $qb->andWhere('e.' . $var . "= :$var")
                    ->setParameter($var, $val);
            }
//            $qb->andWhere($filter);
        }
        $results = $qb->getQuery()->toIterable();
        if (is_null($count)) {
            // slow if not filtered!
            $count = count(iterator_to_array($results, false));
        }
        $results = $qb->getQuery()->toIterable();
        $this->io->title("$count $class");
        if (!$count) {
            return ['numberOfDocuments'=>0];
        }
        if ($subdomain) {
            assert($count == 1, "$count should be one for " . $subdomain);
        }
        $progressBar = $this->getProcessBar($count);
        $progressBar->setMessage("Indexing $class");

        foreach ($results as $idx => $r) {

            // @todo: pass in groups?  Or configure within the class itself?
            // maybe these should come from the ApiPlatform normalizer.

            // we should probably index from the actual api calls.
            // for now, just match the groups in the normalization groups of the entity
//            $groups = ['rp', 'searchable', 'marking', 'translation', sprintf("%s.read", strtolower($indexName))];
            $data = $this->normalizer->normalize($r, null, ['groups' => $groups]);
//            if (count($data['keywords'])) dd($data);
            if (!array_key_exists($primaryKey, $data)) {
//                dd($data, $primaryKey);
                $this->logger->error("No primary key $primaryKey for " . $class);
                break;
            }
            $data['id'] = $data[$primaryKey]; // ??
            if (array_key_exists('keyedTranslations', $data)) {
                $data['_translations'] = $data['keyedTranslations'];
                $data['targetLocales'] = array_keys($data['_translations']);
//                unset($data['keyedTranslations']);
            }
//            dd($r, $data);
//            assert(array_key_exists('_translations', $data), "Missing translations for " .$r::class);
            // if live, only add if indexed and meiliCount
//            dd(array_keys($data), $data['keyedTranslations']);
            // total hack, this doesn't belong in the indexer, but in the deserializer before sending the results back,
            // so somewhere in meili?
//            assert($data['locale'], "Missing locale for $class " . $code);
//            if ($projectLocale = $data['projectLocale']??$data['locale']??false) {
//                $language = Languages::getName($projectLocale);
//                $data['language'] = $language;
//            }

            if ($dump === ($idx+1)) {
                dd($data);
            }
//
            $records[] = $data;
//            if (count($data['tags']??[]) == 0) { continue; dd($data['tags'], $r->getTags()); }

            if (( ($progress = $progressBar->getProgress()) % $batchSize) === 0) {
                $task = $index->addDocuments($records, $primaryKey);
                // wait for the first record, so we fail early and catch the error, e.g. meili down, no index, etc.
                if (!$progress) {
                    $this->meiliService->waitForTask($task);
                }
                $records = [];
            }
            $progressBar->advance();

            if ($limit && ($progressBar->getProgress() >= $limit)) {
                break;
            }
        }
        if (count($records)) {
            $task = $index->addDocuments($records, $primaryKey);
            // if debugging
//            $this->waitForTask($task);
        }


        $progressBar->finish();

        $this->showIndexSettings($index);

        return $this->meiliService->waitUntilFinished($index);

    }

    private function getTranslationArray($entity, $accessor) {
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

    public function getProcessBar(int $total=0): ProgressBar
    {
        // https://jonczyk.me/2017/09/20/make-cool-progressbar-symfony-command/
        $progressBar = new ProgressBar($this->io, $total);
        if ($total) {
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        } else {
            $progressBar->setFormat(' %current% [%bar%] %elapsed:6s% %memory:6s% -- %message%');

        }
        return $progressBar;
    }

    public function showIndexSettings(Indexes $index)
    {
        if ($this->io->isVeryVerbose()) {
            $table=  new Table($this->io);
            $table->setHeaders(['Attributes','Values']);
            try {
                $settings = $index->getSettings();
                foreach ($settings as $var => $val) {
                    if (is_array($val)) {
                        $table->addRow([str_replace('Attributes', '', $var)
                            , join("\n", $val)]);
                    }
                }
            } catch (\Exception $exception) {
                // no settings if it doesn't exist
            }
            $table->render();;
        }

    }


}
