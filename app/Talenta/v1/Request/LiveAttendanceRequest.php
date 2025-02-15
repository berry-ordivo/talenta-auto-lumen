<?php

namespace App\Talenta\v1\Request;

use Carbon\Carbon;
use GuzzleHttp\Cookie\FileCookieJar;
use Symfony\Component\HttpFoundation\Request;

class LiveAttendanceRequest extends AbstractRequest
{
    public $sessionToken;
    /** @var string|null $fileCookieJarName */
    protected ?string $fileCookieJarName = 'live_attendance.cookies';

    /** @var string|null $accessToken */
    protected ?string $accessToken = null;

    /** @var bool $isClockedIn */
    protected bool $isClockedIn = true;

    /** @var bool $isClockedOut */
    protected bool $isClockedOut = true;

    protected bool $isOffDay = false;

    /** @var string $eventTypeClockIn */
    public static string $eventTypeClockIn = 'clock_in';

    /** @var string $eventTypeClockOut */
    public static string $eventTypeClockOut = 'clock_out';

    /** @var array $data */
    protected array $data = [
        'latitude' => null,
        'longitude' => null,
        'event_type' => null,
        'notes' => null,
        'selfie_photo' => null,
        'organisation_user_id' => null,
        'source' => null,
        'schedule_date' => null
    ];

    /** @var array $offDay */
    protected static array $offDay = [
        'saturday',
        'sunday'
    ];

    /** @var array $offDay */
    protected static array $holidays = [
        '01-01-2025',
		'27-01-2025',
        '29-01-2025',
        '17-02-2025',
        '29-03-2025',
        '31-03-2025',
        '01-04-2025',
        '01-05-2025',
        '12-05-2025',
        '29-05-2025',
        '01-06-2025',
        '06-06-2025',
        '27-06-2025',
        '17-08-2025',
        '05-09-2025',
        '25-12-2025',
    ];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $defaultConfig['base_uri'] = 'https://api.mekari.com';

        $config = array_merge($defaultConfig, $config);

        parent::__construct($config);

        $this->data['latitude'] = env('TALENTA_LIVE_ATTENDANCE_LATITUDE');
        $this->data['longitude'] = env('TALENTA_LIVE_ATTENDANCE_LONGITUDE');
        $this->data['notes'] = '';
        $this->data['selfie_photo'] = null;
        $this->data['source'] = env('TALENTA_LIVE_ATTENDANCE_SOURCE', 'mobileweb');
        $this->data['schedule_date'] = $this->currentDate->format(parent::$dateFormat);
    }

    /**
     * @return array
     */
    public function clockIn(): array
    {
        $this->data['event_type'] = self::$eventTypeClockIn;
        if (!$this->isOffDay()) {
            return $this->executorCaller();
        }

        $this->responseData['status']['code'] = '403';
        $this->responseData['status']['message'] = $this->isOffDay() ?
            __('CLOCK_IN_FAILED_DUE_OFF_DAY') : __('CLOCK_IN_TIME_DOES_NOT_MATCH ' . (int)$this?->currentDate);

        return $this->responseData;
    }

    /**
     * @return array
     */
    public function clockOut(): array
    {
        $this->data['event_type'] = self::$eventTypeClockOut;

        if (
            !$this->isOffDay()) {
            return $this->executorCaller();
        }

        $this->responseData['status']['code'] = '403';
        $this->responseData['status']['message'] = $this->isOffDay() ?
            __('CLOCK_OUT_FAILED_DUE_OFF_DAY') : __('CLOCK_OUT_TIME_DOES_NOT_MATCH');

        return $this->responseData;
    }

    /**
     * @return array
     */
    protected function executorCaller(): array
    {
        $token = $this->parseToken();
        $userId = env('TALENTA_USER_ID');

        $this->data['organisation_user_id'] = (string)$userId;

        if ($this->getCompanyId() === null) {
            $this->responseData['status']['code'] = '404';
            $this->responseData['status']['message'] = __('COMPANY_ID_NOT_FOUND');

            return $this->responseData;
        }

        $uri = sprintf(
            '/internal/talenta-attendance-web/v1/organisations/%s/attendance_clocks',
            $this->getCompanyId()
        );

        $option = [
            'json' => $this->data,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token)
            ],
        ];

        return $this->executor(
            Request::METHOD_POST,
            $uri,
            $option
        );
    }

    /**
     * @return bool
     */
    public function isClockedIn(): bool
    {
        return $this->isClockedIn;
    }

    /**
     * @param bool $isClockedIn
     */
    public function setIsClockedIn(bool $isClockedIn): void
    {
        $this->isClockedIn = $isClockedIn;
    }

    /**
     * @return bool
     */
    public function isClockedOut(): bool
    {
        return $this->isClockedOut;
    }

    /**
     * @param bool $isClockedOut
     */
    public function setIsClockedOut(bool $isClockedOut): void
    {
        $this->isClockedOut = $isClockedOut;
    }

    /**
     * @return bool
     */
    public function isOffDay(): bool
    {
        $offDays = env('TALENTA_OFF_DAY', self::$offDay);
        $holidays = self::$holidays;
        $explode = explode(',', $offDays);

        array_walk($explode, function(&$value) {
            $value = strtoupper($value);
        });
        $weekend = in_array(strtoupper($this->currentDate->format('l')), $explode);
        $dayOff = in_array($this->currentDate->timezone('Asia/Jakarta')->format('d-m-Y'), $holidays);

        return $dayOff || $weekend;
    }

    /**
     * @return string|null
     */
    private function parseToken(): ?string
    {
        $sessionToken = $this->parser($this->sessionToken);
        return $sessionToken[1] ?? null;
    }

    /**
     * @return int|null
     */
    // private function parseUserId(): ?int
    // {
    //     $identity = $this->parser('_identity');
    //     $userId = json_decode($identity[1] ?? '', true);

    //     return $userId[0] ?? null;
    // }

    /**
     * @param string $cookieName
     * @return array
     */
    private function parser($string): array
    {
        $decode = urldecode($string);
        $split = substr($decode, strpos($decode, "a:2:") - 0);
        return unserialize($split) ?? [];
    }

    function setSessionToken($sessionToken) {
        $this->sessionToken = $sessionToken;
    }
}
