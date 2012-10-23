<?php

namespace NetteAddons\Manage\Forms;

use NetteAddons\Model,
	NetteAddons\Model\Addon,
	NetteAddons\Model\Tags,
	NetteAddons\Model\Facade\AddonManageFacade,
	NetteAddons\Model\Importers\RepositoryImporterManager,
	NetteAddons\Model\Utils\Licenses,
	NetteAddons\Model\Utils\FormValidators;



/**
 * @author Patrik Votoček
 *
 * @property \NetteAddons\Model\Addon $addon
 */
abstract class AddonForm extends \NetteAddons\Forms\BaseForm
{
	/** @var \NetteAddons\Model\Facade\AddonManageFacade */
	protected $manager;

	/** @var \NetteAddons\Model\Importers\RepositoryImporterManager */
	private $importerManager;

	/** @var \NetteAddons\Model\Tags */
	private $tags;

	/** @var \NetteAddons\Model\Utils\FormValidators */
	private $validators;

	/** @var \NetteAddons\Model\Utils\Licenses */
	private $licenses;

	/** @var array */
	private $descriptionFormats = array();

	/** @var \NetteAddons\Model\Addon */
	private $addon;


	/**
	 * @param \NetteAddons\Model\Facade\AddonManageFacade
	 * @param \NetteAddons\Model\Importers\RepositoryImporterManager
	 * @param \NetteAddons\Model\Tags
	 * @param \NetteAddons\Model\Utils\FormValidators
	 * @param \NetteAddons\Model\Utils\Licenses
	 */
	public function __construct(AddonManageFacade $manager, RepositoryImporterManager $importerManager, Tags $tags, FormValidators $validators, Licenses $licenses)
	{
		$this->manager = $manager;
		$this->importerManager = $importerManager;
		$this->tags = $tags;
		$this->validators = $validators;
		$this->licenses = $licenses;
		parent::__construct();
	}



	/**
	 * @param string
	 * @param string
	 * @return AddonForm
	 */
	public function addDescriptionFormat($id, $name)
	{
		$this->descriptionFormats[$id] = $name;
		$this['descriptionFormat']->setItems($this->descriptionFormats);
		return $this;
	}



	protected function buildForm()
	{
		$this->addText('name', 'Name', NULL, 100)
			->setRequired();
		$this->addText('composerFullName', 'Composer name', NULL, 100)
			->setRequired()
			->addRule(self::PATTERN, 'Invalid composer name', FormValidators::COMPOSER_NAME_RE)
			->addRule($this->validators->isComposerFullNameUnique, 'This composer name has been already taken.');
		$this->addMultiSelect('defaultLicense', 'Default license', $this->licenses->getLicenses(TRUE))
			->setRequired()
			->addRule($this->validators->isLicenseValid, 'Invalid license identifier.');
		$this->addMultiSelect('tags', 'Categories', $this->getCategories());
		$this->addText('repository', 'Repository URL', NULL, 500)
			->addCondition(self::FILLED)
			->addRule(self::URL);
		$this->addText('demo', 'Demo URL', NULL, 500)
			->setType('url')
			->addCondition(self::FILLED)
			->addRule(self::URL);
		$this->addText('shortDescription', 'Short description', NULL, 250)
			->setRequired();
		$this->addTextArea('description', 'Description')
			->setRequired();
		$this->addSelect('descriptionFormat', 'Description format')
			->setDefaultValue('texy')
			->setRequired();
	}



	/**
	 * @return array
	 */
	private function getCategories()
	{
		$categories = array();
		foreach ($this->tags->findMainTags() as $tag) {
			$categories[$tag->id] = $tag->name;
		}
		return $categories;
	}



	/**
	 * @return \NetteAddons\Model\Addon
	 */
	public function getAddon()
	{
		if (!$this->addon) {
			$this->addon = new Addon;
		}
		return $this->addon;
	}



	/**
	 * @param \NetteAddons\Model\Addon
	 * @return AddonForm
	 */
	public function setAddon(Addon $addon)
	{
		$this->addon = $addon;

		if (!is_null($addon->repositoryHosting)) {
			if (is_null($addon->id)) {
				$this->removeComponent($this['repository']);
			}

			if ($addon->defaultLicense) {
				$this->removeComponent($this['defaultLicense']);
			}
		}

		if ($addon->composerFullName) {
			$this->removeComponent($this['composerFullName']);
		}

		$license = $addon->defaultLicense;
		if (is_string($license)) {
			$license = array_map('trim', explode(',', $license));
		}
		$this->setDefaults(array(
			'name' => $addon->name,
			'shortDescription' => $addon->shortDescription,
			'description' => $addon->description,
			'descriptionFormat' => $addon->descriptionFormat,
			'defaultLicense' => $license,
			'repository' => $addon->repository,
			'demo' => $addon->demo,
			'tags' => $addon->getTagsIds(),
		));

		return $this;
	}



	/**
	 * @param array
	 * @return array
	 */
	protected function preProcess(array $values = array())
	{
		$values['repositoryHosting'] = NULL;
		if (!empty($values['repository'])) {
			$values['repository'] = $this->importerManager->normalizeUrl($values['repository']);
			if ($this->importerManager->isValid($values['repository'])) {
				$values['repositoryHosting'] = $this->importerManager->getIdByUrl($values['repository']);
			}
		}
		return $values;
	}

}
