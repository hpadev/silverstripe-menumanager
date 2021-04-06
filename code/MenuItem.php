<?php

namespace Heyday\MenuManager;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\GridField\GridField;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Class MenuItem
 */
class MenuItem extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static $table_name = 'MenuItem';

    /**
     * @var array
     */
    private static $db = [
        // If you want to customise the MenuTitle use this field - leaving blank will use MenuTitle of associated Page
        'MenuTitle' => 'Varchar(255)',
        // This field is used for external links (picking a page from the dropdown will overwrite this link)
        'Link' => 'Text',
        // Sort order
        'Sort' => 'Int',
        // Can be used as a check for adding target="_blank"
        'IsNewWindow' => 'Boolean'
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Page' => 'SilverStripe\CMS\Model\SiteTree', // page the MenuItem refers to
        'MenuSet' => 'Heyday\MenuManager\MenuSet', // parent MenuSet
        'ParentMenuItem' => MenuItem::class,
    ];

    private static $has_many = [
        'MenuItemChildren' => MenuItem::class . '.ParentMenuItem',
    ];

    private static $cascade_deletes = [
        'MenuItemChildren',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'MenuTitle',
        'Page.Title'
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'MenuTitle' => 'Title',
        'Page.Title' => 'Page Title',
        'Link' => 'Link',
        'IsNewWindowNice' => 'Opens in new window?'
    ];

    public function IsNewWindowNice()
    {
        return $this->IsNewWindow ? 'Yes' : 'No';
    }

    /**
     * @var string
     */
    private static $default_sort = 'Sort';

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'MANAGE_MENU_ITEMS' => 'Manage Menu Items'
        ];
    }

    /**
     * @param mixed $member
     * @param array $context
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::check('MANAGE_MENU_ITEMS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        return Permission::check('MANAGE_MENU_ITEMS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        return Permission::check('MANAGE_MENU_ITEMS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canView($member = null)
    {
        return Permission::check('MANAGE_MENU_ITEMS');
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        // $fields = new FieldList();
        //
        // $fields->push(
        //     new TextField('MenuTitle', 'Menu Title (will default to selected page title)')
        // );
        //
        // $fields->push(
        //     new TreeDropdownField(
        //         'PageID',
        //         'Page',
        //         SiteTree::class
        //     )
        // );
        //
        // $fields->push(new TextField('Link', 'Link (use when not specifying a page)'));
        // $fields->push(new CheckboxField('IsNewWindow', 'Open in a new window?'));

        $fields = parent::getCMSFields();

        $fields->removeByName('MenuTitle');
        $fields->removeByName('PageID');
        $fields->removeByName('Link');
        $fields->removeByName('IsNewWindow');
        $fields->removeByName('Sort');
        $fields->removeByName('MenuSetID');
        $fields->removeByName('ParentMenuItemID');

        $fields->addFieldToTab('Root.Main', CheckboxField::create('IsNewWindow', 'Open in a new window?'));
        $fields->addFieldToTab('Root.Main', TextField::create('Link', 'Link (use when not specifying a page)'), 'IsNewWindow');
        $fields->addFieldToTab('Root.Main', TreeDropdownField::create(
            'PageID',
            'Page',
            SiteTree::class
        ), 'Link');
        $fields->addFieldToTab('Root.Main', TextField::create('MenuTitle', 'Menu Title (will default to selected page title)'), 'PageID');

        // $childrenGrid = $fields->fieldByName('Root.MenuItemChildren.MenuItemChildren');
        // if ($childrenGrid) {
        //     $config = $childrenGrid->getConfig();
        //     $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        //     $config->addComponent(new GridFieldDeleteAction());
        //     // https://addons.silverstripe.org/add-ons/undefinedoffset/sortablegridfield
        //     $config->addComponent(new GridFieldSortableRows('Sort'));
        // }

        if ($childrenGrid = $fields->dataFieldByName('MenuItemChildren')) {
            if ($childrenGrid instanceof GridField) {
                $config = $childrenGrid->getConfig();
                $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
                $config->addComponent(new GridFieldDeleteAction());
                // https://addons.silverstripe.org/add-ons/undefinedoffset/sortablegridfield
                $config->addComponent(new GridFieldSortableRows('Sort'));

                $childrenGrid->setTitle('Sub Menu Items');
                $fields->removeByName('MenuItemChildren');
                if (!$this->ParentMenuItem()->exists()) {
                    $fields->addFieldToTab('Root.Main', $childrenGrid);
                }
            }
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return mixed
     */
    public function Parent()
    {
        return $this->MenuSet();
    }

    /**
     * Attempts to return the $field from this MenuItem
     * If $field is not found or it is not set then attempts
     * to return a similar field on the associated Page
     * (if there is one)
     *
     * @param string $field
     * @return mixed
     */
    public function __get($field)
    {
        $default = parent::__get($field);

        if ($default || $field === 'ID') {
            return $default;
        } else {
            $page = $this->Page();

            if ($page instanceof DataObject) {
                if ($page->hasMethod($field)) {
                    return $page->$field();
                } else {
                    return $page->$field;
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->MenuTitle;
    }

    /**
     * Checks to see if a page has been chosen and if so sets Link to null
     * This means that used in conjunction with the __get method above
     * calling $menuItem->Link won't return the Link field of this MenuItem
     * but rather call the Link method on the associated Page
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->PageID != 0) {
            $this->Link = null;
        }
    }
}
