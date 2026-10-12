/**
 * A small calculator engine without eval. Supports + - × ÷, brackets, unary minus and a percent sign that
 * behaves like a pocket calculator:  200 + 10% = 220,  200 − 10% = 180,  200 × 10% = 20,  10% = 0.1
 */

const OPERATORS = { '+': '+', '-': '-', '−': '-', '×': '*', '*': '*', x: '*', '÷': '/', '/': '/' };

export class CalculatorError extends Error {}

function tokenize(expression) {
    const tokens = [];
    let index = 0;

    while (index < expression.length) {
        const char = expression[index];

        if (/\s/.test(char)) {
            index++;
        } else if (/[0-9.]/.test(char)) {
            let end = index;

            while (end < expression.length && /[0-9.]/.test(expression[end])) {
                end++;
            }

            const text = expression.slice(index, end);

            if ((text.match(/\./g) ?? []).length > 1 || text === '.') {
                throw new CalculatorError('Invalid number');
            }

            tokens.push({ type: 'number', value: Number(text) });
            index = end;
        } else if (char in OPERATORS) {
            tokens.push({ type: 'op', value: OPERATORS[char] });
            index++;
        } else if (char === '(' || char === ')' || char === '%') {
            tokens.push({ type: char });
            index++;
        } else {
            throw new CalculatorError(`Unexpected character ${char}`);
        }
    }

    return tokens;
}

/**
 * Evaluates an expression and returns a number. Throws CalculatorError for anything it cannot compute.
 */
export function evaluate(expression) {
    const tokens = tokenize(expression ?? '');
    let position = 0;

    const peek = () => tokens[position];
    const take = () => tokens[position++];

    // factor := '-' factor | number ['%'] | '(' expr ')' ['%']
    function factor() {
        const token = take();

        if (!token) {
            throw new CalculatorError('Unexpected end');
        }

        if (token.type === 'op' && token.value === '-') {
            const inner = factor();

            return { value: -inner.value, percent: inner.percent };
        }

        if (token.type === 'op' && token.value === '+') {
            return factor();
        }

        let result;

        if (token.type === 'number') {
            result = { value: token.value, percent: false };
        } else if (token.type === '(') {
            result = sum();

            if (take()?.type !== ')') {
                throw new CalculatorError('Missing )');
            }
        } else {
            throw new CalculatorError('Unexpected token');
        }

        while (peek()?.type === '%') {
            take();
            result = { value: result.value, percent: true };
        }

        return result;
    }

    function product() {
        let left = factor();

        while (peek()?.type === 'op' && (peek().value === '*' || peek().value === '/')) {
            const operator = take().value;
            const right = factor();
            const divisor = right.percent ? right.value / 100 : right.value;

            if (operator === '/' && divisor === 0) {
                throw new CalculatorError('Division by zero');
            }

            const base = left.percent ? left.value / 100 : left.value;
            left = { value: operator === '*' ? base * divisor : base / divisor, percent: false };
        }

        return left;
    }

    function sum() {
        let left = product();

        while (peek()?.type === 'op' && (peek().value === '+' || peek().value === '-')) {
            const operator = take().value;
            const right = product();
            const base = left.percent ? left.value / 100 : left.value;
            const amount = right.percent ? (base * right.value) / 100 : right.value;
            left = { value: operator === '+' ? base + amount : base - amount, percent: false };
        }

        return left;
    }

    const result = sum();

    if (position < tokens.length) {
        throw new CalculatorError('Unexpected token');
    }

    const value = result.percent ? result.value / 100 : result.value;

    if (!Number.isFinite(value)) {
        throw new CalculatorError('Not a number');
    }

    return Number(value.toFixed(10));
}

/**
 * Evaluates without throwing: null when the expression is empty or incomplete.
 */
export function tryEvaluate(expression) {
    if (!expression || !expression.trim()) {
        return null;
    }

    try {
        return evaluate(expression);
    } catch {
        return null;
    }
}

/**
 * Money-style rounding for display.
 */
export function roundMoney(value) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

/**
 * What the customer gets back, and what is still owed, for an amount paid against a total.
 */
export function settle(total, paid) {
    const owed = roundMoney(total);
    const given = roundMoney(Number(paid) || 0);

    return {
        change: given > owed ? roundMoney(given - owed) : 0,
        remaining: given < owed ? roundMoney(owed - given) : 0,
    };
}

/**
 * Tax on a net amount, and the net amount hidden inside a gross one.
 */
export function addTax(net, ratePercent) {
    return roundMoney(net * (1 + ratePercent / 100));
}

export function removeTax(gross, ratePercent) {
    return roundMoney(gross / (1 + ratePercent / 100));
}

export function applyDiscount(amount, percent) {
    return roundMoney(amount * (1 - percent / 100));
}
