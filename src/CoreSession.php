<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Abc\Session;

use SetBased\Abc\Abc;

/**
 * A session handler that stores the session data in a database table.
 */
class CoreSession implements Session
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The source for randomness.
   *
   * @var string
   */
  public static $entropyFile = '/dev/urandom';

  /**
   * The number of bytes the be read from the source of randomness.
   *
   * @var int
   */
  public static $entropyLength = 32;

  /**
   * The number of seconds before a session expires (default is 20 minutes).
   *
   * @var int
   */
  public static $timeout = 1200;

  /**
   * The session data.
   *
   * @var array
   */
  protected $session;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a secure random token that can be safely used for session IDs. The length of the token is 64 HEX
   * characters.
   *
   * @return string
   */
  private static function getRandomToken()
  {
    $handle = fopen(self::$entropyFile, 'rb');
    $token  = hash('sha256', fread($handle, self::$entropyLength));
    fclose($handle);

    return $token;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the ID of company of the current session.
   *
   * @return int
   *
   * @deprecated Use Abc::$companyResolver->getCmpId() instead.
   */
  public function getCmpId()
  {
    return $this->session['cmp_id'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns stateful double submit token to prevent CSRF attacks.
   *
   * @return string
   */
  public function getCsrfToken()
  {
    return $this->session['ses_csrf_token'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the ID of preferred language of the user of the current session.
   *
   * @return int
   */
  public function getLanId()
  {
    return $this->session['lan_id'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the ID of the profile of the user of the current session.
   *
   * @return int
   */
  public function getProId()
  {
    return $this->session['pro_id'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the ID of the current session.
   *
   * @return int|null
   */
  public function getSesId()
  {
    return $this->session['ses_id'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the session token.
   *
   * @return string
   */
  public function getSessionToken()
  {
    return $this->session['ses_session_token'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the ID of the user of the current session.
   *
   * @return int
   */
  public function getUsrId()
  {
    return $this->session['usr_id'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns true if the user is anonymous (i.e. not a user who has logged in). Otherwise, returns false.
   *
   * @return bool
   */
  public function isAnonymous()
  {
    return $this->session['usr_anonymous'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the session that an user has successfully logged in.
   *
   * @param int $usrId The ID the user.
   */
  public function login($usrId)
  {
    // Return immediately for fake (a.k.a. non-persistent) sessions.
    if ($this->session['ses_id']===null) return;

    $this->session['ses_session_token'] = self::getRandomToken();
    $this->session['ses_csrf_token']    = self::getRandomToken();

    $this->session = Abc::$DL->abcAuthSessionLogin($this->session['cmp_id'],
                                                   $this->session['ses_id'],
                                                   $usrId,
                                                   $this->session['ses_session_token'],
                                                   $this->session['ses_csrf_token']);

    $this->unpackSession();
    $this->setCookies();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Terminates the current session.
   */
  public function logout()
  {
    // Return immediately for fake (a.k.a. non-persistent) sessions.
    if ($this->session['ses_id']===null) return;

    $this->session['ses_session_token'] = self::getRandomToken();
    $this->session['ses_csrf_token']    = self::getRandomToken();

    $this->session = Abc::$DL->abcAuthSessionLogout($this->session['cmp_id'],
                                                    $this->session['ses_id'],
                                                    $this->session['ses_session_token'],
                                                    $this->session['ses_csrf_token']);

    $this->unpackSession();
    $this->setCookies();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Saves the current state of the session.
   */
  public function save()
  {
    // Return immediately for fake (a.k.a. non-persistent) sessions.
    if ($this->session['ses_id']===null) return;

    $serial = (!empty($_SESSION)) ? serialize($_SESSION) : null;
    Abc::$DL->abcAuthSessionUpdateSession($this->session['cmp_id'], $this->session['ses_id'], $serial);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Changes the language of the current session.
   *
   * @param int $lanId The ID of the language.
   */
  public function setLanId($lanId)
  {
    // Return immediately for fake (a.k.a. non-persistent) sessions.
    if ($this->session['ses_id']===null) return;

    $this->session['lan_id'] = $lanId;
    Abc::$DL->abcAuthSessionUpdateLanId($this->session['cmp_id'], $this->session['ses_id'], $lanId);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a session or resumes the current session based on the session cookie.
   */
  public function start()
  {
    $sesSessionToken = $_COOKIE['ses_session_token'] ?? null;
    if ($sesSessionToken===null)
    {
      // Start a new session.
      $this->session = Abc::$DL->abcAuthSessionStartSession(Abc::$companyResolver->getCmpId(),
                                                            Abc::$languageResolver->getLanId(),
                                                            self::getRandomToken(),
                                                            self::getRandomToken());
    }
    else
    {
      $this->session = Abc::$DL->abcAuthSessionGetSession(Abc::$companyResolver->getCmpId(), $sesSessionToken);

      if (empty($this->session))
      {
        // Session has expired and removed from the database or the session token was not generated by this web site.
        // Start a new session with new tokens.
        $this->session = Abc::$DL->abcAuthSessionStartSession(Abc::$companyResolver->getCmpId(),
                                                              Abc::$languageResolver->getLanId(),
                                                              self::getRandomToken(),
                                                              self::getRandomToken());
      }
      elseif (($this->session['ses_last_request'] + self::$timeout)<=time())
      {
        // Session has expired. Restart the session, i.e. delete all data stored in the session and use new tokens.
        $this->session = Abc::$DL->abcAuthSessionRestartSession(Abc::$companyResolver->getCmpId(),
                                                                $this->session['ses_id'],
                                                                Abc::$languageResolver->getLanId(),
                                                                self::getRandomToken(),
                                                                self::getRandomToken());
      }
    }

    $this->unpackSession();
    $this->setCookies();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Sets the session and CSRF cookies.
   */
  protected function setCookies()
  {
    if (isset($_SERVER['HTTPS']))
    {
      $domain = Abc::$canonicalHostnameResolver->getCanonicalHostname();

      // Set session cookie.
      setcookie('ses_session_token',
                $this->session['ses_session_token'],
                false,
                '/',
                $domain,
                true,
                true);

      // Set CSRF cookie.
      setcookie('ses_csrf_token',
                $this->session['ses_csrf_token'],
                false,
                '/',
                $domain,
                true,
                false);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Unpacks the session data and initializes $_SESSION.
   */
  protected function unpackSession()
  {
    if ($this->session['ses_data']!==null)
    {
      $_SESSION = unserialize($this->session['ses_data']);
    }
    else
    {
      $_SESSION = [];
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
