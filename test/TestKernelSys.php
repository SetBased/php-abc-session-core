<?php
declare(strict_types=1);

namespace Plaisio\Session\Test;

use Plaisio\Babel\Babel;
use Plaisio\Babel\CoreBabel;
use Plaisio\C;
use Plaisio\CompanyResolver\CompanyResolver;
use Plaisio\CompanyResolver\UniCompanyResolver;
use Plaisio\Kernel\Nub;
use Plaisio\LanguageResolver\CoreLanguageResolver;
use Plaisio\LanguageResolver\LanguageResolver;
use SetBased\Stratum\MySql\MySqlDefaultConnector;

/**
 * Kernel for testing purposes.
 */
class TestKernelSys extends Nub
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the helper object for deriving the company.
   *
   * @return CompanyResolver
   */
  public function getCompanyResolver(): CompanyResolver
  {
    return new UniCompanyResolver(C::CMP_ID_SYS);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the helper object for retrieving linguistic entities.
   *
   * @return Babel
   */
  protected function getBabel(): Babel
  {
    $babel = new CoreBabel();
    $babel->setLanguage(C::LAN_ID_EN);

    return $babel;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the data layer generated by PhpStratum.
   *
   * @return TestDataLayer
   */
  protected function getDL(): TestDataLayer
  {
    $connector = new MySqlDefaultConnector('localhost', 'test', 'test', 'test');
    $dl        = new TestDataLayer($connector);
    $dl->connect();
    $dl->begin();
    $dl->executeNone('delete from ABC_AUTH_SESSION');
    $dl->executeNone('delete from ABC_AUTH_SESSION_NAMED');
    $dl->executeNone('delete from ABC_AUTH_SESSION_NAMED_LOCK');

    return $dl;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @return LanguageResolver
   */
  protected function getLanguageResolver(): LanguageResolver
  {
    return new CoreLanguageResolver(C::LAN_ID_EN);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
