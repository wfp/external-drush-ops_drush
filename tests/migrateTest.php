<?php

namespace Unish;

/**
 * Tests migrate commands
 *
 * @group commands
 */
class MigrateCase extends CommandUnishTestCase {

  /**
   * The site options to be used when running commands against Drupal.
   *
   * @var array
   */
  protected $siteOptions = array();

  /**
   * Migrate specific options when running commands against Drupal.
   *
   * @var array
   */
  protected $migrateOptions = array();

  /**
   * {@inheritdoc}
   */
  public function setUp() {

    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Migrate manifest is for D8+');
    }

    if (!$sites = $this->getSites()) {
      $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, 'standard');
    }
    $site = key($sites);
    $root = $this->webroot();
    $this->siteOptions = array(
      'root' => $root,
      'uri' => $site,
      'yes' => NULL,
    );
    $this->drush('pm-enable', array('migrate_drupal'), $this->siteOptions);

    // All migrate commands will need this option.
    $this->migrateOptions = $this->siteOptions + array(
        'legacy-db-url' => $this->db_url($site),
        'simulate' => NULL,
        'backend' => NULL,
      );
  }

  /**
   * Test that simple migration works.
   */
  public function testSimpleMigration() {
    $manifest = $this->createManifestFile('- d6_action_settings');
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions);
    $return = $this->parse_backend_output($this->getoutput());
    $this->assertArrayHasKey('d6_action_settings', $return['object'], 'Found migration');
  }

  /**
   * Test multiple migrations that have config.
   */
  public function testMigrationWithConfig() {
    $yaml = "- d6_file:
  source:
    conf_path: sites/assets
  destination:
    source_base_path: destination/base/path
    destination_path_property: uri
- d6_action_settings";
    $manifest = $this->createManifestFile($yaml);
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions);
    $return = $this->parse_backend_output($this->getoutput());

    $this->assertArrayHasKey('d6_file', $return['object'], 'Found migration');

    $this->assertContains('[conf_path] => sites/assets', $return['object']['foo']);
    $this->assertContains('[source_base_path] => destination/base/path', $return['object']['foo']);
    $this->assertContains('[destination_path_property] => uri', $return['object']['foo']);
    $this->assertContains('Importing: d6_action_settings', $return['object']['foo']);
  }

  /**
   * Test that not existent migrations are reported.
   */
  public function testNonExistentMigration() {
    $manifest = $this->createManifestFile('- non_existent_migration');
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions, NULL, NULL, self::EXIT_ERROR);
    $return = $this->parse_backend_output($this->getoutput());
    $error_log = $return['error_log'];
    $this->assertContains('The following migrations were not found: non_existent_migration', $error_log);
  }

  /**
   * Test invalid Yaml files are detected.
   */
  public function testInvalidYamlFile() {
    $invalid_yml = '--- :d6_migration';
    $manifest = $this->createManifestFile($invalid_yml);
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions, NULL, NULL, self::EXIT_ERROR);
    $return = $this->parse_backend_output($this->getoutput());
    $error_log = $return['error_log'];
    $this->assertContains('The following migrations were not found: non_existent_migration', $error_log);
  }

  /**
   * Test with a non-existed manifest files.
   */
  public function testNonExistentFile() {
    $output = $this->drushExpectError(array('/some/file/that/doesnt/exist'));
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions, NULL, NULL, self::EXIT_ERROR);
    $return = $this->parse_backend_output($this->getoutput());
    $error_log = $return['error_log'];
    $this->assertContains('The manifest file does not exist.', $output);
  }

  /**
   * Create a manifest file in the web root with the specified migrations.
   *
   * @param string $yaml
   *   A string of yaml for the migration file.
   *
   * @return string
   *   The path to the manifest file.
   */
  protected function createManifestFile($yaml) {
    $manifest = $this->webroot() . '/manifest.yml';
    file_put_contents($manifest, $yaml);
    return $manifest;
  }
}
