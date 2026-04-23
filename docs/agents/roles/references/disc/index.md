# DISC

В role frontmatter проекта TasK `DISC` используется как компактный internal prompt encoding для настройки личности агента.

Формат записи:
`D6 I8 S3 C4`

Где:
- `D` — [Dominance](dominance.md)
- `I` — [Influence](influence.md)
- `S` — [Steadiness](steadiness.md)
- `C` — [Conscientiousness](conscientiousness.md)

Шкала для каждого измерения: от `0` до `10`.

Интерпретация:
- `0` — признак почти отсутствует
- `5` — умеренно выраженный, сбалансированный уровень
- `10` — доминирующий, определяющий признак

Эта шкала не является официальной psychometric DISC assessment scoring methodology.
Это проектная нормализованная запись, предназначенная для интуитивного чтения LLM и человеком без дополнительных разъяснений.
