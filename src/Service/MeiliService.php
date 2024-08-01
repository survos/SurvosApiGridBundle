<?php

namespace Survos\ApiGrid\Service;

use App\Entity\Core;
use App\Entity\CoreInterface;
use App\Entity\Owner;
use App\Entity\Project;
use App\Entity\Sheet;
use App\Entity\Spreadsheet;
use App\Repository\ProjectRepository;
use App\Service\AppService;
use App\Service\MusDigService;
use App\Service\PdoCacheService;
use App\Service\ProjectService;
use App\Service\SpreadsheetService;
use Doctrine\ORM\EntityManagerInterface;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Model\Property;
use Survos\GridGroupBundle\Model\Schema;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\Reader;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class MeiliService

{
    public function __construct(
        protected ParameterBagInterface $bag,
        protected EntityManagerInterface $entityManager,
        protected ?ClientInterface $httpClient=null,
        private string $meiliHost,
        private string $meiliKey,
        private array $config=[],
        private ?LoggerInterface $logger = null,
    )
    {
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }


    public function reset(Project|string $indexName)
    {
        if (!is_string($indexName)) {
            $indexName = $indexName->getMeiliIndexName();
        }
        $client = $this->getMeiliClient();
        try {
            $index = $client->getIndex($indexName);
            $response = $client->deleteIndex($indexName);
            $task = $this->waitForTask($response['taskUid'], $index);
//            $this->io()->info("Deletion Task is at " . $task['status']);
            $this->logger->info("Index " . $indexName . " has been deleted.");

        } catch (ApiException $exception) {
            if ($exception->errorCode == 'index_not_found') {
                try {
//                    $this->io()->info("Index $indexName does not exist.");
                } catch (\Exception) {
                    //
                }
//                    continue;
            } else {
                dd($exception);
            }
        }

    }

    public function waitForTask(array|string|int $taskId, ?Indexes $index = null, bool $stopOnError = true, mixed $dataToDump=null): array
    {

        if (is_array($taskId)) {
            $taskId = $taskId['taskUid'];
        }
        if ($index) {
            $task = $index->waitForTask($taskId);
        } else {
            // e.g index creation, when we don't have an index.  there's probably a better way.
            while (
                ($task = $this->getMeiliClient()->getTask($taskId))
                && (($status = $task['status']) && !in_array($status, ['failed', 'succeeded']))) {

                if (isset($this->logger)) {
//                    $this->logger->info(sprintf("Task %s is at %s", $taskId, $status));
                }
            };
            if ($status == 'failed') {
                if ($stopOnError) {
                    throw new \Exception("Task has failed " . $task['error']['message']);
                }
            }
        }

        return $task;

    }

    public function getPrefixedIndexName(string $indexName)
    {
        if ($prefix = $this->getConfig()['meiliPrefix']) {
            if (!str_starts_with($indexName, $prefix)) {
                $indexName = $prefix . $indexName;
            }
        }
        return $indexName;
    }

    /**
     * @param \Meilisearch\Endpoints\Indexes $index
     * @param SymfonyStyle $io
     * @param string|null $indexName
     * @return array
     */
    public function waitUntilFinished(Indexes $index, SymfonyStyle $io = null): array
    {
        do {
            $index->fetchInfo();
            $info = $index->fetchInfo();
            $stats = $index->stats();
            $isIndexing = $stats['isIndexing'];
            $indexName = $index->getUid();
            if ($this->logger) {
                $this->logger->info(sprintf("\n%s is %s with %d documents",
                    $indexName, $isIndexing ? 'indexing' : 'ready', $stats['numberOfDocuments']));
            }
            if ($isIndexing) {
                sleep(1);
            }
        } while ($isIndexing);
        return $stats;
    }


    public function getMeiliClient(): Client
    {
        static $client;
        if (!$client) {
            if (!class_exists('Meilisearch\\Client')) {
                throw new \Exception("Meili client not installed, run\n\n composer require meilisearch/meilisearch-php symfony/http-client nyholm/psr7:^1.0");
            }
            $client = new Client($this->meiliHost, $this->meiliKey, httpClient: $this->httpClient);
        }
        return $client;
    }

    public function getIndex(string $indexName, string $key = 'id', bool $autoCreate=true): ?Indexes
    {
        $indexName = $this->getPrefixedIndexName($indexName);
        $this->loadExistingIndexes();
        static $indexes = [];
        if (!$index = $indexes[$indexName] ?? null) {
            if ($autoCreate) {
                $index = $this->getOrCreateIndex($indexName, $key);
                $indexes[$indexName] = $index;
            }
        }
        return $index;
    }

    public function loadExistingIndexes()
    {
        return;
        $client = $this->getMeiliClient();
        do {
            $indexes = $client->getIndexes();
            dd($indexes);
        } while ($nextPage);
    }

    public function getOrCreateIndex(string $indexName, string $key = 'id', bool $autoCreate=true): ?Indexes
    {
        $client = $this->getMeiliClient();
        try {
            $index = $client->getIndex($indexName);
        } catch (ApiException $exception) {
            if ($exception->httpStatus === 404) {
                if ($autoCreate) {
                    $task = $this->waitForTask($this->getMeiliClient()->createIndex($indexName, ['primaryKey' => $key]));
//            $this->getMeiliClient()->createIndex($indexName, ['primaryKey' => $key]);
                    $index = $client->getIndex($indexName);
                } else {
                    $index = null;
                }
            } else {
                dd($exception, $exception::class);
            }
        }
        return $index;

    }


    public function applyToIndex(string $indexName, callable $callback, int $batch = 50)
    {
        $index = $this->getMeiliClient()->index($indexName);

        $documents = $index->getDocuments((new DocumentsQuery())->setLimit(0));
        $total = $documents->getTotal();
        $currentPosition = 0;
        $progressBar = $this->getProcessBar($total);

        while ($currentPosition < $total) {
            $documents = $index->getDocuments((new DocumentsQuery())->setOffset($currentPosition)->setLimit($batch));
            $currentPosition += $documents->count();
            foreach ($documents->getIterator() as $row) {
                $progressBar->advance();
                $callback($row, $index);
            }
            $currentPosition++;
        }
        $progressBar->finish();

    }


}
