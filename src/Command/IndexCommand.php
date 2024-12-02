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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales=[],

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
                // actually, ApiResource or GetCollection
                $apiRouteAttributes = $meta->getReflectionClass()->getAttributes(ApiResource::class);
                foreach ($apiRouteAttributes as $attribute) {
                    $args = $attribute->getArguments();
                    // @todo: this could also be inside of the operation!
                    if (array_key_exists('normalizationContext', $args)) {
                        assert(array_key_exists('groups', $args['normalizationContext']), "Add a groups to " . $meta->getName());
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
        if ($output->isVerbose() && !count($apiRouteAttributes)) {
            $output->writeln("Skipping $class, not an API Resource");
        }


        $this->io = new SymfonyStyle($input, $output);

        foreach ($classes as $class=>$groups) {
            $indexName = $this->meiliService->getPrefixedIndexName((new \ReflectionClass($class))->getShortName());

            $this->io->title($indexName);
            if ($reset=$input->getOption('reset')) {
                $this->meiliService->reset($indexName);
            }

            // skip if no documents?  Obviously, docs could be added later, e.g. an Owner record after import
//            $task = $this->waitForTask($this->getMeiliClient()->createIndex($indexName, ['primaryKey' => Instance::DB_CODE_FIELD]));

            // pk of meili  index might be different than doctine pk, e.g. $imdbId
            $index = $this->configureIndex($class, $indexName);
            $batchSize = $input->getOption('batch-size');

            $stats = $this->indexClass($class, $index, batchSize: $batchSize, indexName: $indexName, groups: $groups,
                limit: $input->getOption('limit'),
                filter: $input->getOption('filter') ? $filterArray: null,
                primaryKey: $index->getPrimaryKey(),
                dump: $input->getOption('dump'),
            );

            $this->io->success($indexName . ' Document count:' .$stats['numberOfDocuments']);
            $this->meiliService->waitUntilFinished($index);

            if ($this->io->isVerbose()) {
                $stats = $index->stats();
                $this->io->write(json_encode($stats, JSON_PRETTY_PRINT));
                $this->io->write(json_encode($index->getSettings(), JSON_PRETTY_PRINT));
                // now what?

            }
            $this->io->success($this->getName() . ' ' . $class . ' finished indexing to ' . $indexName);

        }

        $this->io->success($this->getName() . ' complete.');
        return self::SUCCESS;

    }

    private function configureIndex(string $class, string $indexName): Indexes
    {

//        $reflection = new \ReflectionClass($class);
//        $classAttributes = $reflection->getAttributes();
//        $filterAttributes = [];
//        $sortableAttributes = [];
        $settings = $this->datatableService->getSettingsFromAttributes($class);
        $idFields = $this->datatableService->getFieldsWithAttribute($settings, 'is_primary');
        $primaryKey = count($idFields) ? $idFields[0] : 'id';

        $map = [
            'es' => 'spa',
            'en' => 'eng',
            'de' => 'deu',
            'hi' => 'hin',
            'fr' => 'fra',
            'da' => 'dan',
        ];
        $searchableAttrs = [];

        $localizedAttributes = [];
        foreach ($this->enabledLocales as $locale) {
            $locale3 = $map[$locale];
            $localizedAttributes[] = ['locales' => [$locale3],
                'attributePatterns' => [sprintf('_translations.%s.*',$locale)]];
        }

        $index = $this->meiliService->getIndex($indexName, $primaryKey);
//        $index->updateSortableAttributes($this->datatableService->getFieldsWithAttribute($settings, 'sortable'));
//        $index->updateSettings(); // could do this in one call

            $results = $index->updateSettings($settings = [
                'localizedAttributes' => $localizedAttributes,
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

    private function indexClass(string  $class,
                                Indexes $index,
                                int $batchSize,
                                ?string $indexName=null,
                                array $groups=[],
                                int $limit=0,
                                ?array $filter=[],
                                int $dump=0,
                                ?string $primaryKey=null,
                                ?string $subdomain=null,
    ): array
    {
        $startingAt = 0;
        $records = [];
        $primaryKey ??= $index->getPrimaryKey();
        $count = 0;
        $qb = $this->entityManager->getRepository($class)->createQueryBuilder('e');

        if ($filter) {
            foreach ($filter as $var => $val) {
                $qb->andWhere('e.' . $var . "= :$var")
                    ->setParameter($var, $val);
            }
//            $qb->andWhere($filter);
        }
        $total = (clone $qb)->select("count(e.{$index->getPrimaryKey()})")->getQuery()->getSingleScalarResult();
        $this->io->title("Indexing $class ($total records, batches of $batchSize) ");
        if (!$total) {
            return ['numberOfDocuments'=>0];
        }

        $query = $qb->getQuery();
        $progressBar = $this->getProcessBar($total);
        $progressBar->setMessage("Indexing $class ($total records, batches of $batchSize) ");

        do {
        if ($batchSize) {
            assert($count < $total, "count $count >= total $total");
            $query
                ->setFirstResult($startingAt)
                ->setMaxResults($batchSize);
//            $this->io->writeln("Fetching $startingAt ($batchSize)");
        }
        $results = $query->toIterable();
//        if (is_null($count)) {
//            // slow if not filtered!
//            $count = count(iterator_to_array($results, false));
//        }
//            $results = $qb->getQuery()->toIterable();
            $startingAt += $batchSize;
//            $count += count(iterator_to_array($results, false)); //??

        if ($subdomain) {
            assert($count == 1, "$count should be one for " . $subdomain);
        }
        foreach ($results as $idx => $r) {
            $count++;
            // @todo: pass in groups?  Or configure within the class itself?
            // maybe these should come from the ApiPlatform normalizer.

            // we should probably index from the actual api calls.
            // for now, just match the groups in the normalization groups of the entity
//            $groups = ['rp', 'searchable', 'marking', 'translation', sprintf("%s.read", strtolower($indexName))];
            $data = $this->normalizer->normalize($r, null, ['groups' => $groups]);
            assert(array_key_exists('rp', $data), json_encode($data));

            if (!array_key_exists($primaryKey, $data)) {
                $this->logger->error($msg = "No primary key $primaryKey for " . $class);
                assert(false, $msg . "\n" . join("\n", array_keys($data)));
                return ['numberOfDocuments'=>0];
                break;
            }
            $data['id'] = $data[$primaryKey]; // ??
            if (array_key_exists('keyedTranslations', $data)) {
                $data['_translations'] = $data['keyedTranslations'];
                $data['targetLocales'] = array_keys($data['_translations']);
//                unset($data['keyedTranslations']);
            }
//            assert(array_key_exists('_translations', $data), "Missing translations for " .$r::class);
            // if live, only add if indexed and meiliCount
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

            if ($batchSize && (($progress = $progressBar->getProgress()) % $batchSize) === 0) {
                $task = $index->addDocuments($records, $primaryKey);
                // wait for the first record, so we fail early and catch the error, e.g. meili down, no index, etc.
                if (!$progress) {
                    $this->meiliService->waitForTask($task);
                }
//                $this->io->writeln("Flushing " . count($records));
                $records = [];
            }
            $progressBar->advance();
            assert($count == $progressBar->getProgress(), "$count  <> " . $progressBar->getProgress());

            if ($limit && ($progressBar->getProgress() >= $limit)) {
                $count = $total; // hack for breaking out of loop
                break;
            }
        }
//            $this->io->writeln("$count of $total loaded, this batch:" . count($records));
        if ($startingAt > $total) {
    //            dump($count, $total, $startingAt);
        }
        } while ( ($count < $total)) ;

        $progressBar->finish();
        // if there are some that aren't batched...
            $this->io->writeln("Final Flush " . count($records));
            $task = $index->addDocuments($records, $primaryKey);
            // if debugging
            $this->meiliService->waitForTask($task);
        if (count($records)) {
        }



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
