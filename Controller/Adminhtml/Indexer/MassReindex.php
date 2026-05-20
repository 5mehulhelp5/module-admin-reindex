<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\Adminhtml\Indexer;

use ETechFlow\AdminReindex\Model\Config;
use ETechFlow\AdminReindex\Model\Performance\Profiler;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * POST: /admin/etechflow_admin_reindex/indexer/massReindex
 *
 * Receives the indexer IDs the admin selected on the Index Management grid
 * (or `indexer_ids=__all__` for the Reindex All shortcut), rebuilds each one,
 * surfaces success/failure via admin messages, and redirects back to the
 * Index Management list.
 *
 * Each indexer's rebuild is wrapped in its own try/catch so a single bad
 * indexer doesn't take down the rest of the batch.
 */
class MassReindex extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdminReindex::run';

    /**
     * Constructor.
     *
     * @param Context                  $context
     * @param IndexerRegistry          $indexerRegistry
     * @param IndexerCollectionFactory $indexerCollectionFactory
     * @param Config                   $config
     * @param LoggerInterface          $logger
     */
    public function __construct(
        Context $context,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly IndexerCollectionFactory $indexerCollectionFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('ETechFlow Admin Reindex is disabled or unlicensed on this domain.'));
            return $redirect->setPath('indexer/indexer/list');
        }

        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid security token. Please reload the page and try again.'));
            return $redirect->setPath('indexer/indexer/list');
        }

        $rawIds = $this->getRequest()->getParam('indexer_ids', []);
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
        }
        $rawIds = array_filter((array) $rawIds);

        if (!$rawIds) {
            $this->messageManager->addWarningMessage(__('Please select at least one indexer to reindex.'));
            return $redirect->setPath('indexer/indexer/list');
        }

        // "__all__" sentinel triggers a full reindex of every indexer.
        if (in_array('__all__', $rawIds, true)) {
            $rawIds = array_keys($this->indexerCollectionFactory->create()->getItems());
        }

        $span = Profiler::start('ETechFlow_AR_MassReindex');
        try {
            $succeeded = [];
            $failures  = [];

            foreach ($rawIds as $indexerId) {
                $indexerId = (string) $indexerId;
                try {
                    $indexer = $this->indexerRegistry->get($indexerId);
                    $start = microtime(true);
                    $indexer->reindexAll();
                    $duration = round(microtime(true) - $start, 2);
                    $succeeded[] = sprintf('%s (%.2fs)', $indexer->getTitle() ?: $indexerId, $duration);
                } catch (\Throwable $e) {
                    $failures[$indexerId] = $e->getMessage();
                    $this->logger->error('[ETechFlow_AdminReindex] ' . $indexerId . ': ' . $e->getMessage());
                }
            }

            if ($succeeded) {
                $this->messageManager->addSuccessMessage(__(
                    '%1 indexer(s) rebuilt successfully: %2',
                    count($succeeded),
                    implode(', ', $succeeded)
                ));
            }
            foreach ($failures as $id => $msg) {
                $this->messageManager->addErrorMessage(__('Failed to reindex %1: %2', $id, $msg));
            }
        } finally {
            Profiler::stop($span);
        }

        return $redirect->setPath('indexer/indexer/list');
    }
}
