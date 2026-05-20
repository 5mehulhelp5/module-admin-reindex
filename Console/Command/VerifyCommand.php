<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Console\Command;

use ETechFlow\AdminReindex\Controller\Adminhtml\Indexer\MassReindex;
use ETechFlow\AdminReindex\Model\Config;
use ETechFlow\AdminReindex\Model\LicenseValidator;
use ETechFlow\AdminReindex\Plugin\IndexerGridPlugin;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento etechflow:ar:verify`
 *
 * Headless end-to-end check of the ETechFlow_AdminReindex module.
 * Confirms classes resolve via DI, the licence validator evaluates,
 * the config reads, the controller / plugin instantiate, and a
 * real Magento indexer can be loaded through `IndexerRegistry`.
 *
 * Idempotent. No DB writes. Safe to run on production.
 */
class VerifyCommand extends Command
{
    public function __construct(
        private readonly AppState $appState,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LicenseValidator $licenseValidator,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:ar:verify')
            ->setDescription('Headless end-to-end check of the ETechFlow Admin Reindex module.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }

        $output->writeln('<info>=== ETechFlow Admin Reindex verify ===</info>');
        $output->writeln('');

        $allPassed = true;

        try {
            // 1. License validator evaluates without throwing
            $this->step($output, '1. LicenseValidator evaluates without throwing');
            $host = $this->licenseValidator->getCurrentHost();
            $isProduction = $this->licenseValidator->isProductionEnvironment();
            $isDev = $this->licenseValidator->isDevHost();
            $isValid = $this->licenseValidator->isValid();
            $this->pass($output, sprintf(
                'host=%s; production=%s; dev_host=%s; valid=%s',
                $host !== '' ? $host : '(empty)',
                $isProduction ? 'yes' : 'no',
                $isDev ? 'yes' : 'no',
                $isValid ? 'yes' : 'no'
            ));

            // 2. Config reachable
            $this->step($output, '2. Config.isEnabled() returns a boolean without throwing');
            $enabled = $this->config->isEnabled();
            $this->pass($output, 'enabled=' . ($enabled ? 'yes' : 'no'));

            // 3. Plugin class resolves via DI
            $this->step($output, '3. Plugin IndexerGridPlugin resolves via DI');
            $plugin = $this->objectManager->get(IndexerGridPlugin::class);
            if (!$plugin instanceof IndexerGridPlugin) {
                throw new \RuntimeException('Plugin DI returned wrong type');
            }
            $this->pass($output);

            // 4. Controller class resolves via DI
            $this->step($output, '4. Controller MassReindex resolves via DI');
            $controller = $this->objectManager->get(MassReindex::class);
            if (!$controller instanceof MassReindex) {
                throw new \RuntimeException('Controller DI returned wrong type');
            }
            $this->pass($output);

            // 5. IndexerRegistry returns the catalog product indexer
            $this->step($output, '5. IndexerRegistry returns at least one core indexer');
            $registry = $this->objectManager->get(\Magento\Framework\Indexer\IndexerRegistry::class);
            $indexer = $registry->get('catalog_product_attribute');
            if (!$indexer || $indexer->getId() !== 'catalog_product_attribute') {
                throw new \RuntimeException('IndexerRegistry returned no catalog indexer');
            }
            $this->pass($output, 'catalog_product_attribute indexer found');

            // 6. Indexer collection returns rows
            $this->step($output, '6. Indexer collection returns at least one indexer');
            $collection = $this->objectManager->get(
                \Magento\Indexer\Model\Indexer\CollectionFactory::class
            )->create();
            $count = count($collection->getItems());
            if ($count < 1) {
                throw new \RuntimeException('Indexer collection returned zero items');
            }
            $this->pass($output, sprintf('%d indexers registered', $count));

            $output->writeln('');
            $output->writeln('<info>✅ ALL CHECKS PASSED. v1.0.0 verified.</info>');
        } catch (\Throwable $e) {
            $allPassed = false;
            $output->writeln('');
            $output->writeln('<error>❌ FAIL: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>at ' . $e->getFile() . ':' . $e->getLine() . '</error>');
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    private function step(OutputInterface $output, string $label): void
    {
        $output->write('  ' . $label . ' ... ');
    }

    private function pass(OutputInterface $output, string $detail = ''): void
    {
        $output->writeln('<info>OK</info>' . ($detail !== '' ? " ({$detail})" : ''));
    }
}
