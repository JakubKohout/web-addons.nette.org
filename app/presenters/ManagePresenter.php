<?php

namespace NetteAddons;

use NetteAddons\Forms\Form,
	NetteAddons\Model\Addon,
	NetteAddons\Model\Users,
	NetteAddons\Model\AddonVersion,
	NetteAddons\Model\AddonVersions,
	NetteAddons\Model\Utils\Validators,
	NetteAddons\Model\Utils\FormValidators,
	NetteAddons\Model\Utils\VersionParser;



final class ManagePresenter extends Manage\BasePresenter
{
	/** @var AddonVersions */
	private $versions;

	/** @var Users */
	private $users;

	/** @var Validators */
	private $validators;

	/** @var FormValidators */
	private $formValidators;

	/** @var VersionParser */
	private $versionParser;



	public function injectValidators(Validators $validators, FormValidators $formValidators)
	{
		$this->validators = $validators;
		$this->formValidators = $formValidators;
	}



	public function injectVersionParser(VersionParser $parser)
	{
		$this->versionParser = $parser;
	}



	public function injectAddons(AddonVersions $versions, Users $users)
	{
		$this->versions = $versions;
		$this->users = $users;
	}



	/**
	 * Creates a new form for addon information.
	 *
	 * @return Manage\Forms\AddAddonForm
	 */
	protected function createComponentAddAddonForm()
	{
		$form = $this->getContext()->addAddonForm;

		if ($this->addon) {
			$form->setAddon($this->addon);
		}
		$form->setUser($this->getUser()->identity);
		$form->setToken($this->token);

		$form->onSuccess[] = $this->addAddonFormSubmitted;

		return $form;
	}



	/**
	 * Handles the new addon form submission.
	 *
	 * @param Manage\Forms\AddAddonForm
	 */
	public function addAddonFormSubmitted(Manage\Forms\AddAddonForm $form)
	{
		if ($form->valid) {
			$this->addon = $form->addon;
			$this->token = $form->token;

			$imported = (bool) $this->addon->repositoryHosting; // TODO: remove

			if ($imported) {
				$this->flashMessage('Addon created.');
				$this->redirect('importVersions');

			} else {
				$this->flashMessage('Addon created. Now it\'s time to add the first version.');
				$this->redirect('createVersion');
			}
		}
	}



	/**
	 * @return Manage\Forms\ImportAddonForm
	 */
	protected function createComponentImportAddonForm()
	{
		$form = $this->getContext()->importAddonForm;
		$form->setUser($this->getUser()->identity);
		$form->onSuccess[] = $this->importAddonFormSubmitted;
		return $form;
	}


	/**
	 * @param Manage\Forms\ImportAddonForm
	 */
	public function importAddonFormSubmitted(Manage\Forms\ImportAddonForm $form)
	{
		if ($form->valid) {
			$this->token = $form->token;

			$this->flashMessage('Addon has been successfully loaded.');
			$this->redirect('createAddon');
		}
	}



	/**
	 * @param int|NULL addon id
	 */
	public function renderCreateVersion($addonId = NULL)
	{

	}



	/**
	 * @return Manage\Forms\AddVersionForm
	 */
	protected function createComponentAddVersionForm()
	{
		$form = $this->getContext()->addAddonVersionForm;

		$form->setAddon($this->addon);
		$form->setToken($this->token);
		$form->setUser($this->getUser()->identity);

		$form->onSuccess[] = $this->addVersionFormSubmitted;

		return $form;
	}



	/**
	 * @param Manage\Forms\AddVersionForm
	 */
	public function addVersionFormSubmitted(Manage\Forms\AddVersionForm $form)
	{
		if ($form->valid) {
			$this->token = $form->token;

			if ($this->addon->id) {
				$this->flashMessage('Version created.');
				$this->redirect('Detail:', $this->addon->id);

			} else {
				$this->redirect('finish');
			}
		}
	}



	/**
	 * @param int
	 */
	public function renderCheckVersions($addonId)
	{
		if (!$this->addon->repositoryHosting) {
			$this->error();
		}

		$owner = $this->users->createIdentity($this->users->find($this->addon->userId));

		try {
			$importer = $this->createAddonImporter($this->addon->repository);
			$result = $this->manager->updateVersions($this->addon, $importer, $owner);

		} catch (\NetteAddons\IOException $e) {
			$this->flashMessage('Version importing failed. Try again later.', 'error');
			$this->redirect('Detail:', $this->addon->id);
		}

		if (count($result['conflicted']) === 0 && count($result['new']) === 0) {
			$this->flashMessage('Nothing new…');
		} else {
			try {
				foreach ($result['new'] as $version) {
					$this->versions->add($version);
				}
				foreach ($result['conflicted'] as $conflict) {
					$conflict['b']->id = $conflict['a']->id;
					$this->versions->update($conflict['b']);
				}
				$this->flashMessage('Versions has been updated.');
			} catch (\PDOException $e) {
				$this->flashMessage('Version importing failed. Try again later.', 'error');
			}
		}

		$this->redirect('Detail:', $this->addon->id);
	}



	public function handleImportVersions()
	{
		if (!$this->addon) {
			$this->error();
		}

		try {
			$importer = $this->createAddonImporter($this->addon->repository);
			$this->manager->importVersions($this->addon, $importer, $this->getUser()->getIdentity());
			$this->manager->storeAddon($this->getSessionKey(), $this->addon);
			$this->redirect('finish');

		} catch (\NetteAddons\NotSupportedException $e) {
			$this->error();
		}
	}



	/**
	 * Finish the addon creation
	 */
	public function actionFinish()
	{
		if ($this->addon === NULL) {
			$this->error();
		}

		try {
			$this->addons->add($this->addon);
			$this->manager->destroyAddon($this->getSessionKey());
			$this->flashMessage('Addon was successfully registered.');
			$this->redirect('Detail:', $this->addon->id);

		} catch (\NetteAddons\DuplicateEntryException $e) {
			$this->flashMessage("Adding new addon failed.", 'danger');
			$this->redirect('createAddon');
		}
	}



	public function actionEditAddon($addonId)
	{
		$this['subMenu']->setAddon($this->addon);
	}



	public function renderEditAddon($addonId)
	{
		$this->template->addon = $this->addon;
	}



	/**
	 * @return Manage\Forms\EditAddonForm
	 */
	protected function createComponentEditAddonForm()
	{
		if (!$this->addon) {
			$this->error('Addon not found.');
		}

		$form = $this->getContext()->editAddonForm;

		$form->setAddon($this->addon);

		$form->onSuccess[] = $this->editAddonFormSubmitted;

		return $form;
	}



	/**
	 * @param Manage\Forms\EditAddonForm
	 */
	public function editAddonFormSubmitted(Manage\Forms\EditAddonForm $form)
	{
		if ($form->valid) {
			$this->addon = $form->addon;

			$this->flashMessage('Addon saved.');
			$this->redirect('Detail:', $this->addon->id);
		}
	}
}
