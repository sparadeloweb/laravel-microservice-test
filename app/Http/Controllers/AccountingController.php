<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    /**
     * Obtiene y devuelve los recibos.
     *
     * @return \Illuminate\Http\JsonResponse
    */
    public function getReceipts()
    {
        try {
            // Lógica para obtener los recibos
            $receipts = []; // Ejemplo de datos

            if (empty($receipts)) {
                return response()->json(['message' => 'No receipts found'], 404);
            }

            return response()->json($receipts);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene y devuelve las entradas contables.
     *
     * @return \Illuminate\Http\JsonResponse
    */
    public function getEntries()
    {
        try {
            // Lógica para obtener las entradas contables
            $entries = []; // Ejemplo de datos

            if (empty($entries)) {
                return response()->json(['message' => 'No entries found'], 404);
            }

            return response()->json($entries);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene y devuelve una entrada contable específica.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
    */
    public function getEntry($id)
    {
        try {
            // Lógica para obtener una entrada específica
            $entry = []; // Ejemplo de datos

            if (empty($entry)) {
                return response()->json(['message' => 'Entry not found'], 404);
            }

            return response()->json($entry);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene y devuelve los sobres enviados.
     *
     * @return \Illuminate\Http\JsonResponse
    */
    public function getSentCfes()
    {
        try {
            $rut = config('pymo.rut');
            if (empty($rut)) {
                return response()->json(['message' => 'Company RUT is not set'], 400);
            }

            $cookies = $this->login();

            if (is_null($cookies)) {
                return response()->json(['message' => 'Login failed'], 401);
            }

            $companySentCfes = $this->getCompanySentCfes($rut, $cookies);

            if (is_null($companySentCfes)) {
                return response()->json(['message' => 'No sent CFEs found'], 404);
            }

            return response()->json($companySentCfes);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene los sobres enviados de la empresa.
     *
     * @param string $rut
     * @param array $cookies
     * @return array|null
    */
    private function getCompanySentCfes(string $rut, array $cookies): ?array
    {
        $response = Http::withCookies($cookies, parse_url(config('pymo.pymo_host'), PHP_URL_HOST))
            ->get(config('pymo.pymo_host') . ':' . config('pymo.pymo_port') . '/' . config('pymo.pymo_version') . '/companies/' . $rut . '/sentCfes?l=1');

        if ($response->failed() || !isset($response->json()['payload']['companySentCfes'])) {
            return null;
        }

        return $response->json()['payload']['companySentCfes'];
    }

    /**
     * Obtiene y devuelve la configuración de la contabilidad.
     *
     * @return \Illuminate\Http\JsonResponse
    */
    public function getSettings()
    {
        try {
            $rut = config('pymo.rut');
            if (empty($rut)) {
                return response()->json(['message' => 'Company RUT is not set'], 400);
            }

            $cookies = $this->login();

            if (is_null($cookies)) {
                return response()->json(['message' => 'Login failed'], 401);
            }

            $companyInfo = $this->getCompanyInfo($rut, $cookies);
            $logoUrl = $this->getCompanyLogo($rut, $cookies);

            return response()->json([
                'companyInfo' => $companyInfo,
                'logoUrl' => $logoUrl,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Guarda el RUT de la empresa.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
    */
    public function saveRut(Request $request)
    {
        try {
            $request->validate([
                'rut' => 'required|string|max:255',
            ]);

            // Guarda el RUT en el archivo .env
            $this->setEnvironmentValue('COMPANY_RUT', $request->rut);

            return response()->json(['message' => 'RUT guardado correctamente.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Sube el logo de la empresa.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
    */
    public function uploadLogo(Request $request)
    {
        try {
            $request->validate([
                'logo' => 'required|file',
            ]);

            $rut = config('pymo.rut');
            if (empty($rut)) {
                return response()->json(['message' => 'Company RUT is not set'], 400);
            }

            $cookies = $this->login();

            if (is_null($cookies)) {
                return response()->json(['message' => 'Login failed'], 401);
            }

            $logoResponse = Http::withCookies($cookies, parse_url(config('pymo.pymo_host'), PHP_URL_HOST))
                ->attach('logo', $request->file('logo')->get(), 'logo.jpg')
                ->post(config('pymo.pymo_host') . ':' . config('pymo.pymo_port') . '/' . config('pymo.pymo_version') . '/companies/' . $rut . '/logo');

            if ($logoResponse->successful()) {
                return response()->json(['message' => 'Logo actualizado correctamente.']);
            }

            return response()->json(['error' => 'Error al actualizar el logo.'], 500);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Realiza el login y devuelve las cookies de la sesión.
     *
     * @return array|null
    */
    private function login(): ?array
    {
        $loginResponse = Http::post(config('pymo.pymo_host') . ':' . config('pymo.pymo_port') . '/' . config('pymo.pymo_version') . '/login', [
            'email' => config('pymo.pymo_user'),
            'password' => config('pymo.pymo_password'),
        ]);

        if ($loginResponse->failed()) {
            return null;
        }

        $cookies = $loginResponse->cookies();
        $cookieJar = [];

        foreach ($cookies as $cookie) {
            $cookieJar[$cookie->getName()] = $cookie->getValue();
        }

        return $cookieJar;
    }

    /**
     * Obtiene el logo de la empresa y lo guarda localmente.
     *
     * @param string $rut
     * @param array $cookies
     * @return string|null
    */
    private function getCompanyLogo(string $rut, array $cookies): ?string
    {
        $logoResponse = Http::withCookies($cookies, parse_url(config('pymo.pymo_host'), PHP_URL_HOST))
            ->get(config('pymo.pymo_host') . ':' . config('pymo.pymo_port') . '/' . config('pymo.pymo_version') . '/companies/' . $rut . '/logo');

        if ($logoResponse->failed()) {
            return null;
        }

        return $this->saveLogoLocally($logoResponse->body());
    }

    /**
     * Guarda la imagen del logo en almacenamiento local.
     *
     * @param string $imageContent
     * @return string
    */
    private function saveLogoLocally(string $imageContent): string
    {
        $logoPath = 'public/assets/img/logos/company_logo.jpg';
        Storage::put($logoPath, $imageContent);

        return Storage::url($logoPath);
    }

    /**
     * Obtiene la información de la empresa.
     *
     * @param string $rut
     * @param array $cookies
     * @return array|null
    */
    private function getCompanyInfo(string $rut, array $cookies): ?array
    {
        $companyResponse = Http::withCookies($cookies, parse_url(config('pymo.pymo_host'), PHP_URL_HOST))
            ->get(config('pymo.pymo_host') . ':' . config('pymo.pymo_port') . '/' . config('pymo.pymo_version') . '/companies/' . $rut);

        if ($companyResponse->failed() || !isset($companyResponse->json()['payload']['company'])) {
            return null;
        }

        return $companyResponse->json()['payload']['company'];
    }

    /**
     * Establece el valor de una variable en el archivo .env.
     *
     * @param string $key
     * @param string $value
     * @return void
    */
    private function setEnvironmentValue(string $key, string $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key . '=' . env($key),
                $key . '=' . $value,
                file_get_contents($path)
            ));
        }
    }

    /**
     * Maneja las excepciones y devuelve una respuesta JSON.
     *
     * @param \Exception $e
     * @return \Illuminate\Http\JsonResponse
    */
    private function handleException(\Exception $e)
    {
        Log::error($e->getMessage(), ['exception' => $e]);

        return response()->json([
            'error' => 'An unexpected error occurred',
            'message' => $e->getMessage()
        ], 500);
    }
}
