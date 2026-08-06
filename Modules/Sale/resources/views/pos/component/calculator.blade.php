<!-- Calculator Modal -->
<div class="modal fade" id="modal_pos_calculator" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content pos-calculator-modal">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title">{{ __('Calculator') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="calculator-container">
                    <div class="calculator-display mb-2">
                        <input type="text" class="form-control text-end fs-4 fw-bold" id="calculator-display" value="0" readonly>
                    </div>
                    <div class="calculator-buttons">
                        <div class="row g-2">
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="clear">C</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="clear-all">CE</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="backspace">
                                    <i class="ti tabler-backspace"></i>
                                </button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary w-100 calculator-btn" data-value="/">÷</button>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="7">7</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="8">8</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="9">9</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary w-100 calculator-btn" data-value="*">×</button>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="4">4</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="5">5</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="6">6</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary w-100 calculator-btn" data-value="-">−</button>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="1">1</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="2">2</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="3">3</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-primary w-100 calculator-btn" data-value="+">+</button>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value="0">0</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-light w-100 calculator-btn" data-value=".">.</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-success w-100 calculator-btn" data-value="=">=</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pos-calculator-modal .modal-content {
    border-radius: 10px;
    overflow: hidden;
}

.calculator-display input {
    height: 60px;
    font-size: 1.5rem !important;
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
}

.calculator-buttons .btn {
    height: 50px;
    font-size: 1.1rem;
    font-weight: 500;
    border: 1px solid #dee2e6;
}

.calculator-buttons .btn:hover {
    transform: scale(0.95);
    transition: transform 0.1s;
}

.calculator-buttons .btn-primary {
    background-color: #696cff;
    border-color: #696cff;
}

.calculator-buttons .btn-success {
    background-color: #71dd37;
    border-color: #71dd37;
}
</style>
