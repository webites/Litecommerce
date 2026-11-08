<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

enum CorePermission: string
{
    case AccessPanel = 'core.panel.access';
    case ManageStaff = 'core.staff.manage';
    case ManageStoreSettings = 'core.store.settings.manage';
    case ManageEmail = 'core.email.manage';
    case ManageCatalog = 'core.catalog.manage';
    case ManagePricing = 'core.pricing.manage';
    case ManageInventory = 'core.inventory.manage';
    case ManageDelivery = 'core.delivery.manage';
    case ManageCoupons = 'core.coupons.manage';
    case ViewOrders = 'core.orders.view';
    case ManageOrders = 'core.orders.manage';
    case ManageRefunds = 'core.refunds.manage';
    case ManageContent = 'core.content.manage';
    case ManageExtensions = 'core.extensions.manage';
    case ManageThemes = 'core.themes.manage';
    case ManageUpdates = 'core.updates.manage';
    case UseTestOrders = 'core.test_orders.use';
    case ViewAudit = 'core.audit.view';
}
