<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Plugin;

use ETechFlow\AdminReindex\Model\Config;
use Magento\Framework\AuthorizationInterface;
use Magento\Indexer\Block\Backend\Grid;

/**
 * Adds the "Reindex" mass-action option to the Index Management grid.
 *
 * The "Reindex All" button is added separately via layout XML
 * (view/adminhtml/layout/indexer_indexer_list.xml).
 *
 * Hidden when the admin user lacks the `ETechFlow_AdminReindex::run`
 * ACL resource, or when the module is licence-invalid / disabled in
 * admin config.
 */
class IndexerGridPlugin
{
    /**
     * Constructor.
     *
     * @param AuthorizationInterface $authorization
     * @param Config                 $config
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly Config $config
    ) {
    }

    /**
     * Inject the "Reindex" item into the grid's mass-action block.
     *
     * @param Grid $subject
     * @return void
     */
    public function beforeToHtml(Grid $subject): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->authorization->isAllowed('ETechFlow_AdminReindex::run')) {
            return;
        }

        $massaction = $subject->getMassactionBlock();
        if (!$massaction) {
            return;
        }

        // If we've already added the item (block may render twice in some
        // admin flows), skip the second pass.
        try {
            if ($massaction->getItem('etechflow_reindex')) {
                return;
            }
        } catch (\Throwable $e) {
            // Some Magento versions throw when an item doesn't exist; ignore.
        }

        $massaction->addItem('etechflow_reindex', [
            'label'   => __('Reindex'),
            'url'     => $subject->getUrl('etechflow_admin_reindex/indexer/massReindex'),
            'confirm' => __('Are you sure you want to reindex the selected items? This can take a few minutes on large catalogs.'),
        ]);
    }
}
