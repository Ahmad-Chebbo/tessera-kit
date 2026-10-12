export const blankGroup = () => ({ type: 'group', logic: 'all', children: [] });

/**
 * The value a condition starts with for a fact and operator.
 */
export function defaultValue(fact, operator) {
    if (operator === 'between') {
        return ['', ''];
    }

    if (operator === 'in' || operator === 'not_in') {
        return [];
    }

    return fact?.type === 'bool' ? true : '';
}

export function blankCondition(facts) {
    const fact = facts[0];
    const operator = fact?.operators?.[0] ?? 'eq';

    return { type: 'condition', field: fact?.key ?? '', operator, value: defaultValue(fact, operator), params: {} };
}

/**
 * Switches a condition to another fact, keeping the operator when the new fact allows it.
 */
export function changeField(condition, fact) {
    const operator = fact.operators.includes(condition.operator) ? condition.operator : fact.operators[0];

    return { ...condition, field: fact.key, operator, value: defaultValue(fact, operator), params: {} };
}

export function changeOperator(condition, fact, operator) {
    const wasList = ['in', 'not_in', 'between'].includes(condition.operator);
    const isList = ['in', 'not_in', 'between'].includes(operator);
    const keep = wasList === isList && (condition.operator === operator || (operator !== 'between' && condition.operator !== 'between'));

    return { ...condition, operator, value: keep ? condition.value : defaultValue(fact, operator) };
}
