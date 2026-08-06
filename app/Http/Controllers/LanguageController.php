<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class LanguageController extends Controller
{
    /**
     * Available locales in the application
     *
     * @var array
     */
    protected $availableLocales = ['en', 'ar', 'bn', 'fr', 'es', 'si', 'hi', 'ur', 'vi'];

    /**
     * Switch the application language
     *
     * @param  string  $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switch($locale)
    {
        // Validate locale
        if (!in_array($locale, $this->availableLocales)) {
            abort(404, 'Language not found');
        }

        // Store locale in session
        Session::put('locale', $locale);

        // Redirect back to the previous page
        return Redirect::back();
    }
}
