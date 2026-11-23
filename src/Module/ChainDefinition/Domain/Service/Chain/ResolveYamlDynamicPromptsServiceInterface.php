<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain;

/**
 * Интерфейс резолвера prompt-конфигурации dynamic-цепочки из raw YAML.
 *
 * Загрузка содержимого prompt-файлов (I/O) выделена из загрузчика цепочек:
 * загрузчик отвечает за разбор структуры цепочек, резолвер промптов — за I/O.
 *
 * Каждый элемент prompts может быть:
 * - путь к файлу (относительно каталога YAML-файла) — если файл существует,
 *   содержимое читается и обрезается по краям (trim);
 * - инлайн-текст — используется как есть.
 *
 * Обязательные ключи валидируются fail-fast: отсутствующий или пустой ключ
 * ломает загрузку конфигурации немедленно, а не при первом раунде динамической
 * цепочки.
 */
interface ResolveYamlDynamicPromptsServiceInterface
{
    /**
     * Резолвит промпты dynamic-цепочки: файл → содержимое, иначе инлайн-текст.
     *
     * @param string $chainName имя цепочки (только для диагностики исключений)
     * @param string $yamlPath путь к загружаемому YAML-файлу; пути промптов
     *                         резолвятся относительно его каталога
     * @param array<string, mixed> $rawPrompts секция `prompts` из YAML
     *
     * @return array<string, string> ключ промпта → готовый текст промпта
     *
     * @throws \InvalidArgumentException если обязательный ключ отсутствует или пуст
     * @throws \RuntimeException         если файл промпта не читается
     */
    public function resolve(string $chainName, string $yamlPath, array $rawPrompts): array;
}
