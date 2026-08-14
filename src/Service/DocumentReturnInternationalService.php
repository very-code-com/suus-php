<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Return of documents to sender - international variant (GG).
 *
 * SUUS symbol: StdDokumentyZwrotneINiezwrotneGrid3
 * Available for INTERNATIONAL orders only (WS PK 1.0). Using it on a domestic
 * order is rejected by {@see \VeryCodeCom\Suus\Internal\Validator\ShipmentValidator}
 * unless the service route restrictions are relaxed via
 * {@see \VeryCodeCom\Suus\Validation\ValidationPolicy}.
 *
 * @api
 */
final class DocumentReturnInternationalService extends AbstractDocumentReturnService
{
    public function getSymbol(): string
    {
        return 'StdDokumentyZwrotneINiezwrotneGrid3';
    }
}
