<?php
namespace Concrete\Core\Install\StartingPoint\Installer\Routine\Base;

use Concrete\Core\Install\StartingPoint\Installer\Routine\AbstractRoutine;

class SetupSitePermissionsRoutine extends AbstractRoutine
{

    public function getText(): string
    {
        return t('Setting site permissions.');
    }


}