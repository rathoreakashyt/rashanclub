$(function () {
    "use strict";
    
    let base_url = $('#base_url').val();
    let isFullscreen = false;

    // Calculator functionality - General Calculator
    let calculatorDisplay = $('#calculator-display');
    let calculatorCurrentValue = '0';
    let calculatorPreviousValue = null;
    let calculatorOperation = null;
    let calculatorWaitingForNewValue = false;
    let calculatorExpression = ''; // Track the expression being built

    // Initialize calculator
    function initCalculator() {
        calculatorCurrentValue = '0';
        calculatorPreviousValue = null;
        calculatorOperation = null;
        calculatorWaitingForNewValue = false;
        calculatorExpression = '';
        updateCalculatorDisplay();
    }

    function updateCalculatorDisplay() {
        if (calculatorExpression) {
            calculatorDisplay.val(calculatorExpression);
            return;
        }
    
        let displayValue = calculatorCurrentValue;
    
        if (displayValue.includes('.')) {
            displayValue = displayValue.replace(/\.?0+$/, '');
        }
    
        calculatorDisplay.val(displayValue || '0');
    }

    function inputNumber(num) {
        // Convert num to string to ensure proper concatenation
        num = String(num);
        
        if (calculatorWaitingForNewValue) {
            // Starting new number after operation
            calculatorCurrentValue = num;
            calculatorWaitingForNewValue = false;
            // Update expression with new number
            if (calculatorOperation && calculatorPreviousValue !== null) {
                calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation) + ' ' + num;
            } else {
                calculatorExpression = num;
            }
        } else {
            // If current value is '0', replace it, otherwise append
            if (calculatorCurrentValue === '0') {
                calculatorCurrentValue = num;
            } else {
                calculatorCurrentValue += num;
            }
            
            // Update expression if there's an operation
            if (calculatorOperation && calculatorPreviousValue !== null) {
                calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation) + ' ' + calculatorCurrentValue;
            } else {
                calculatorExpression = calculatorCurrentValue;
            }
        }
        updateCalculatorDisplay();
    }

    function inputDecimal() {
        if (calculatorWaitingForNewValue) {
            calculatorCurrentValue = '0.';
            calculatorWaitingForNewValue = false;
            // Update expression with new decimal number
            if (calculatorOperation && calculatorPreviousValue !== null) {
                calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation) + ' 0.';
            } else {
                calculatorExpression = '0.';
            }
        } else {
            // Only add decimal if it doesn't already exist
            if (calculatorCurrentValue.indexOf('.') === -1) {
                calculatorCurrentValue += '.';
                
                // Update expression if there's an operation
                if (calculatorOperation && calculatorPreviousValue !== null) {
                    calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation) + ' ' + calculatorCurrentValue;
                } else {
                    calculatorExpression = calculatorCurrentValue;
                }
            }
        }
        updateCalculatorDisplay();
    }

    function getOperationSymbol(op) {
        switch(op) {
            case '+': return '+';
            case '-': return '−';
            case '*': return '×';
            case '/': return '÷';
            default: return '';
        }
    }

    function performOperation(op) {
        const inputValue = parseFloat(calculatorCurrentValue);
        
        // If there's already an operation and we're waiting for new value, just update the operation
        if (calculatorOperation && calculatorWaitingForNewValue) {
            calculatorOperation = op;
            // Update expression with new operation
            calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(op);
            updateCalculatorDisplay();
            return;
        }
        
        if (calculatorPreviousValue === null) {
            // First operation - just store the value
            calculatorPreviousValue = inputValue;
            calculatorExpression = inputValue + ' ' + getOperationSymbol(op);
        } else if (calculatorOperation) {
            // Calculate previous operation first
            const result = calculate(calculatorPreviousValue, inputValue, calculatorOperation);
            calculatorCurrentValue = formatResult(result);
            calculatorPreviousValue = result;
            calculatorExpression = result + ' ' + getOperationSymbol(op);
        } else {
            // Just update previous value
            calculatorPreviousValue = inputValue;
            calculatorExpression = inputValue + ' ' + getOperationSymbol(op);
        }
        
        calculatorWaitingForNewValue = true;
        calculatorOperation = op;
        updateCalculatorDisplay();
    }

    function calculate(prev, current, op) {
        let result;
        switch (op) {
            case '+':
                result = prev + current;
                break;
            case '-':
                result = prev - current;
                break;
            case '*':
                result = prev * current;
                break;
            case '/':
                if (current === 0) {
                    return 'Error';
                }
                result = prev / current;
                break;
            default:
                return current;
        }
        
        // Handle very large or very small numbers
        if (isNaN(result) || !isFinite(result)) {
            return 'Error';
        }
        
        return result;
    }

    function formatResult(value) {
        if (value === 'Error') {
            return 'Error';
        }
        
        // Convert to string and remove unnecessary trailing zeros
        let str = String(value);
        
        // If it's a decimal number, remove trailing zeros
        if (str.includes('.')) {
            str = str.replace(/\.?0+$/, '');
        }
        
        return str;
    }

    function clearCalculator() {
        calculatorCurrentValue = '0';
        calculatorPreviousValue = null;
        calculatorOperation = null;
        calculatorWaitingForNewValue = false;
        calculatorExpression = '';
        updateCalculatorDisplay();
    }

    function clearEntry() {
        if (calculatorWaitingForNewValue) {
            // If waiting for new value, clear the operation
            calculatorOperation = null;
            calculatorWaitingForNewValue = false;
            calculatorExpression = '';
        } else {
            calculatorCurrentValue = '0';
            // Update expression if there's an operation
            if (calculatorOperation && calculatorPreviousValue !== null) {
                calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation);
            } else {
                calculatorExpression = '';
            }
        }
        updateCalculatorDisplay();
    }

    function backspace() {

        // If there's an expression, work on the expression string
        if (calculatorExpression && calculatorExpression.trim() !== '') {
            // Remove last character from expression (including spaces)
            let newExpression = calculatorExpression;
            newExpression = newExpression.slice(0, -1);
            
            // Remove trailing spaces
            newExpression = newExpression.trim();
            
            // Parse the remaining expression to update calculator state
            parseExpressionToState(newExpression);
            return;
        }
        
        // If waiting for new value after operator, allow clearing the operation
        if (calculatorWaitingForNewValue) {
            calculatorOperation = null;
            calculatorWaitingForNewValue = false;
            calculatorExpression = String(calculatorPreviousValue);
            calculatorCurrentValue = String(calculatorPreviousValue);
            calculatorPreviousValue = null;
            updateCalculatorDisplay();
            return;
        }
        
        // Normal backspace on current value
        if (calculatorCurrentValue.length > 1) {
            calculatorCurrentValue = calculatorCurrentValue.slice(0, -1);
        } else {
            calculatorCurrentValue = '0';
        }
        
        // Update expression if there's an operation
        if (calculatorOperation && calculatorPreviousValue !== null) {
            calculatorExpression = calculatorPreviousValue + ' ' + getOperationSymbol(calculatorOperation) + ' ' + calculatorCurrentValue;
        } else {
            calculatorExpression = '';
        }
        
        updateCalculatorDisplay();
    }

    function parseExpressionToState(expression) {
        if (!expression || expression.trim() === '') {
            // Empty expression - reset calculator
            calculatorCurrentValue = '0';
            calculatorPreviousValue = null;
            calculatorOperation = null;
            calculatorWaitingForNewValue = false;
            calculatorExpression = '';
            updateCalculatorDisplay();
            return;
        }
        
        // Parse expression like "20 + 2" or "20 +" or "20"
        const trimmed = expression.trim();
        
        // Match numbers (including decimals and partial numbers)
        const numberPattern = /^-?\d*\.?\d*$/;
        
        // Split by spaces
        const parts = trimmed.split(/\s+/);
        
        if (parts.length === 1) {
            // Just a number (could be partial like "2" from "20")
            const num = parts[0];
            if (numberPattern.test(num) || num === '' || num === '-') {
                calculatorCurrentValue = num || '0';
                calculatorPreviousValue = null;
                calculatorOperation = null;
                calculatorWaitingForNewValue = false;
                calculatorExpression = num || '0';
            }
        } else if (parts.length === 2) {
            // Number and operator like "20 +" or "20 +" (incomplete)
            const num = parts[0];
            const opSymbol = parts[1];
            const op = getOperationFromSymbol(opSymbol);
            
            if (numberPattern.test(num) && op) {
                calculatorPreviousValue = parseFloat(num) || 0;
                calculatorOperation = op;
                calculatorCurrentValue = '0';
                calculatorWaitingForNewValue = true;
                calculatorExpression = expression;
            } else if (numberPattern.test(num)) {
                // Just a number if operator is invalid or partial
                calculatorCurrentValue = num;
                calculatorPreviousValue = null;
                calculatorOperation = null;
                calculatorWaitingForNewValue = false;
                calculatorExpression = num;
            }
        } else if (parts.length >= 3) {
            // Full expression like "20 + 2" or "20 + 20"
            const num1 = parts[0];
            const opSymbol = parts[1];
            const num2 = parts.slice(2).join(''); // Join remaining parts (handle multi-digit)
            
            const op = getOperationFromSymbol(opSymbol);
            
            if (numberPattern.test(num1) && op) {
                calculatorPreviousValue = parseFloat(num1) || 0;
                calculatorOperation = op;
                
                if (numberPattern.test(num2) || num2 === '') {
                    calculatorCurrentValue = num2 || '0';
                    calculatorWaitingForNewValue = num2 === '';
                    calculatorExpression = expression;
                } else {
                    // Invalid second number, treat as waiting for input
                    calculatorCurrentValue = '0';
                    calculatorWaitingForNewValue = true;
                    calculatorExpression = num1 + ' ' + opSymbol;
                }
            } else if (numberPattern.test(num1)) {
                // Invalid operator, treat as just first number
                calculatorCurrentValue = num1;
                calculatorPreviousValue = null;
                calculatorOperation = null;
                calculatorWaitingForNewValue = false;
                calculatorExpression = num1;
            }
        }
        
        updateCalculatorDisplay();
    }

    function getOperationFromSymbol(symbol) {
        switch(symbol) {
            case '+': return '+';
            case '−': return '-';
            case '×': return '*';
            case '÷': return '/';
            default: return null;
        }
    }

    function equals() {
        // Only calculate if there's an operation and previous value
        if (!calculatorOperation || calculatorPreviousValue === null) {
            return;
        }
        
        const inputValue = parseFloat(calculatorCurrentValue);
        const result = calculate(calculatorPreviousValue, inputValue, calculatorOperation);
        
        // Format and store result
        calculatorCurrentValue = formatResult(result);
        calculatorPreviousValue = null;
        calculatorOperation = null;
        calculatorWaitingForNewValue = true;
        calculatorExpression = ''; // Clear expression, show only result
        
        updateCalculatorDisplay();
    }

    // Calculator button click handler
    $(document).on('click', '.calculator-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        // Use attr to get the raw data-value attribute
        let value = $btn.attr('data-value');
        
        // Convert to string for consistent comparison
        value = String(value);
        
        // Handle number input (0-9) - check if it's a single digit
        if (/^[0-9]$/.test(value)) {
            inputNumber(value);
        } 
        // Handle decimal point
        else if (value === '.') {
            inputDecimal();
        } 
        // Handle operations
        else if (value === '+' || value === '-' || value === '*' || value === '/') {
            performOperation(value);
        } 
        // Handle equals
        else if (value === '=') {
            equals();
        } 
        // Handle clear all
        else if (value === 'clear') {
            clearCalculator();
        } 
        // Handle clear entry
        else if (value === 'clear-all') {
            clearEntry();
        } 
        // Handle backspace
        else if (value === 'backspace') {
            backspace();
        }
    });

    // Calculator modal show handler
    $(document).on('shown.bs.modal', '#modal_pos_calculator', function() {
        initCalculator();
    });

    // Open calculator
    $('#pos-calculator-btn').on('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_calculator'));
        modal.show();
    });

    // Print Last Invoice
    $('#pos-print-last-invoice-btn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: route('pos.last-sale.get'),
            method: 'GET',
            success: function(response) {
                if (response.status === 'success' && response.data && response.data.encrypted_id) {
                    // Use the same invoice popup function as after saving sale
                    if (typeof window.openInvoicePopup === 'function') {
                        window.openInvoicePopup(response.data.encrypted_id);
                    } else {
                        // Fallback to regular window.open if function not available
                        const printUrl = base_url + route('sale.print-invoice', { sale: response.data.encrypted_id }, false, Ziggy);
                        window.open(printUrl, '_blank');
                    }
                } else {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification('No sale found to print');
                    } else {
                        alert('No sale found to print');
                    }
                }
            },
            error: function(xhr) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to get last sale invoice');
                } else {
                    alert('Failed to get last sale invoice');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Fullscreen/Maximize functionality
    $('#pos-fullscreen-btn').on('click', function() {
        if (!isFullscreen) {
            // Enter fullscreen
            const elem = document.documentElement;
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
            isFullscreen = true;
            $('#pos-fullscreen-icon').removeClass('tabler-maximize').addClass('tabler-minimize');
            $(this).attr('data-bs-original-title', 'Exit Fullscreen').tooltip('update');
        } else {
            // Exit fullscreen
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            isFullscreen = false;
            $('#pos-fullscreen-icon').removeClass('tabler-minimize').addClass('tabler-maximize');
            $(this).attr('data-bs-original-title', 'Fullscreen').tooltip('update');
        }
    });

    // Listen for fullscreen change events
    $(document).on('fullscreenchange webkitfullscreenchange msfullscreenchange', function() {
        if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
            isFullscreen = false;
            $('#pos-fullscreen-icon').removeClass('tabler-minimize').addClass('tabler-maximize');
            $('#pos-fullscreen-btn').attr('data-bs-original-title', 'Fullscreen').tooltip('update');
        }
    });
});
