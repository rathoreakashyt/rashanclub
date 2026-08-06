<nav
  class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
  id="layout-navbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
      <i class="icon-base ti tabler-menu-2 icon-md"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center">
      <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
        <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
          <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
        </a>
      </div>
      <div class="nav-item d-none ps-2" id="installAppWrapper">
        <button type="button" class="btn btn-sm btn-primary" id="installApp" title="{{ __('Install App') }}">
          <i class="icon-base ti tabler-download me-1"></i>{{ __('Install App') }}
        </button>
      </div>
    </div>

    <!-- /Search -->

    <ul class="navbar-nav flex-row align-items-center ms-md-auto">

      <li class="nav-item dropdown">
        <a
          class="nav-link btn btn-icon btn-text-secondary rounded-pill"
          data-bs-toggle="tooltip"
          data-bs-placement="bottom"
          data-bs-original-title="{{ __('POS') }}"
          href="{{ route('pos.index') }}"
          target="_blank"
          title="{{ __('POS') }}">
          <i class="icon-base ti tabler-moneybag icon-22px text-heading"></i>
        </a>
      </li>

      <li class="nav-item dropdown-language dropdown">
        <a
          class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          href="javascript:void(0);"
          data-bs-toggle="dropdown">
          <i class="icon-base ti tabler-language icon-22px text-heading"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          @foreach (collect(config('app.available_locales', []))->sortBy(fn ($locale) => config('app.locale_names.' . $locale, ucfirst($locale)))->values() as $locale)
            <li>
              <a class="dropdown-item {{ app()->getLocale() === $locale ? 'active' : '' }}" href="{{ route('language.switch', $locale) }}">
                <span>{{ config('app.locale_names.' . $locale, ucfirst($locale)) }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      </li>
      <!--/ Language -->

      <!-- Style Switcher -->
      <li class="nav-item dropdown">
        <a
          class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          id="nav-theme"
          href="javascript:void(0);"
          data-bs-toggle="dropdown">
          <i class="icon-base ti tabler-sun icon-22px theme-icon-active text-heading"></i>
          <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
          <li>
            <button
              type="button"
              class="dropdown-item align-items-center active"
              data-bs-theme-value="light"
              aria-pressed="false">
              <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>Light</span>
            </button>
          </li>
          <li>
            <button
              type="button"
              class="dropdown-item align-items-center"
              data-bs-theme-value="dark"
              aria-pressed="true">
              <span
                ><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i
                >Dark</span
              >
            </button>
          </li>
        </ul>
      </li>
      <!-- / Style Switcher-->

      

      <!-- User -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a
          class="nav-link dropdown-toggle hide-arrow p-0"
          href="javascript:void(0);"
          data-bs-toggle="dropdown">
          <div class="avatar">
            <img src="{{ session('user.photo') }}" alt class="rounded-circle" />
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item mt-0" href="pages-account-settings-account.html">
              <div class="d-flex align-items-center">
                <div class="flex-shrink-0 me-2">
                  <div class="avatar">
                    <img src="{{ session('user.photo') }}" alt class="rounded-circle" />
                  </div>
                </div>
                <div class="flex-grow-1">
                  <h6 class="mb-0">{{ session('user.name') }}</h6>
                  <small class="text-body-secondary">{{ session('user.role') }}</small>
                </div>
              </div>
            </a>
          </li>
          <li>
            <div class="dropdown-divider my-1 mx-n2"></div>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('user.update-profile') }}">
              <i class="icon-base ti tabler-user me-3 icon-md"></i
              ><span class="align-middle">{{ __('My Profile') }}</span>
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('user.update-profile') }}#passwordPart">
              <i class="icon-base ti tabler-key me-3 icon-md"></i
              ><span class="align-middle">{{ __('Change Password') }}</span>
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('user.update-profile') }}#securityPart">
              <i class="icon-base ti tabler-help-circle me-3 icon-md"></i
              ><span class="align-middle">{{ __('Security Question') }}</span>
            </a>
          </li>
          <li>
            <div class="dropdown-divider my-1 mx-n2"></div>
          </li>
          <li>
            <div class="d-grid px-2 pt-2 pb-1">
              <div class="btn btn-sm btn-danger d-flex logout-btn">
                <small class="align-middle">{{ __('Logout') }}</small>
                <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
              </div>
            </div>
          </li>
        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>
</nav>