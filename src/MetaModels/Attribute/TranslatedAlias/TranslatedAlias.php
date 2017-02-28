<?php

/**
 * This file is part of MetaModels/attribute_translatedalias.
 *
 * (c) 2012-2016 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedAlias
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Andreas Isaak <info@andreas-isaak.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2012-2016 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_translatedalias/blob/master/LICENSE LGPL-3.0
 * @filesource
 */

namespace MetaModels\Attribute\TranslatedAlias;

use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Controller\ReplaceInsertTagsEvent;
use MetaModels\Attribute\TranslatedReference;

/**
 * This is the MetaModelAttribute class for handling translated text fields.
 *
 * @package    MetaModels
 * @subpackage AttributeTranslatedAlias
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class TranslatedAlias extends TranslatedReference
{
    /**
     * {@inheritdoc}
     */
    protected function getValueTable()
    {
        return 'tl_metamodel_translatedtext';
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(
            parent::getAttributeSettingNames(),
            array(
                'talias_fields',
                'isunique',
                'force_talias',
                'alwaysSave'
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinition($arrOverrides = array())
    {
        $arrFieldDef = parent::getFieldDefinition($arrOverrides);

        $arrFieldDef['inputType'] = 'text';

        // We do not need to set mandatory, as we will automatically update our value when isunique is given.
        if ($this->get('isunique')) {
            $arrFieldDef['eval']['mandatory'] = false;
        }

        // If "force_alias" is ture set alwaysSave to true.
        if ($this->get('force_alias')) {
            $arrFieldDef['eval']['alwaysSave'] = true;
        }

        return $arrFieldDef;
    }

    /**
     * {@inheritdoc}
     */
    public function modelSaved($objItem)
    {
        $arrValue = $objItem->get($this->getColName());
        // Alias already defined and no update forced, get out!
        if ($arrValue && !empty($arrValue['value']) && (!$this->get('force_talias'))) {
            return;
        }

        $arrAlias = array();
        foreach (deserialize($this->get('talias_fields')) as $strAttribute) {
            $arrValues  = $objItem->parseAttribute($strAttribute['field_attribute'], 'text', null);
            $arrAlias[] = $arrValues['text'];
        }

        $dispatcher   = $this->getMetaModel()->getServiceContainer()->getEventDispatcher();
        $replaceEvent = new ReplaceInsertTagsEvent(implode('-', $arrAlias));
        $dispatcher->dispatch(ContaoEvents::CONTROLLER_REPLACE_INSERT_TAGS, $replaceEvent);

        // Implode with '-', replace inserttags and strip HTML elements.
        $strAlias = standardize(strip_tags($replaceEvent->getBuffer()));

        // We need to fetch the attribute values for all attributes in the alias_fields and update the database
        // and the model accordingly.
        if ($this->get('isunique')) {
            // Ensure uniqueness.
            $strLanguage  = $this->getMetaModel()->getActiveLanguage();
            $strBaseAlias = $strAlias;
            $arrIds       = array($objItem->get('id'));
            $intCount     = 2;
            while (array_diff($this->searchForInLanguages($strAlias, array($strLanguage)), $arrIds)) {
                $strAlias = $strBaseAlias . '-' . ($intCount++);
            }
        }

        $arrData = $this->widgetToValue($strAlias, $objItem->get('id'));

        $this->setTranslatedDataFor(
            array
            (
                $objItem->get('id') => $arrData
            ),
            $this->getMetaModel()->getActiveLanguage()
        );
        $objItem->set($this->getColName(), $arrData);
    }
}
