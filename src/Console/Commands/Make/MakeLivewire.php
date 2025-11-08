<?php

namespace InterNACHI\Modular\Console\Commands\Make;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportConsoleCommands\Commands\MakeCommand;
use Livewire\Livewire;

if (class_exists(MakeCommand::class)) {
    class MakeLivewire extends MakeCommand
    {
        use Modularize;

        public function getAliases(): array
        {
            return ['make:livewire', 'livewire:make'];
        }

        public function handle()
        {
            $module = $this->module();

            if ($module) {
                Config::set('livewire.class_namespace', $module->qualify('Livewire'));
                Config::set('livewire.view_path', $module->path('resources/views/livewire'));
            }

            $this->parser = new \Livewire\Features\SupportConsoleCommands\Commands\ComponentParser(
                config('livewire.class_namespace'),
                config('livewire.view_path'),
                $this->argument('name'),
                $this->option('stub')
            );

            if (! $this->isClassNameValid($name = $this->parser->className())) {
                $this->line("<options=bold,reverse;fg=red> WHOOPS! </> 😳 \n");
                $this->line("<fg=red;options=bold>Class is invalid:</> {$name}");

                return;
            }

            if ($this->isReservedClassName($name)) {
                $this->line("<options=bold,reverse;fg=red> WHOOPS! </> 😳 \n");
                $this->line("<fg=red;options=bold>Class is reserved:</> {$name}");

                return;
            }

            $force = $this->option('force');
            $inline = $this->option('inline');
            $test = $this->option('test') || $this->option('pest');
            $testType = $this->option('pest') ? 'pest' : 'phpunit';

            $showWelcomeMessage = $this->isFirstTimeMakingAComponent();

            $class = $this->createClass($force, $inline);
            $view = $this->createView($force, $inline);

            if ($test) {
                $test = $this->createTest($force, $testType);
            }

            if ($class || $view) {
                $this->line("<options=bold,reverse;fg=green> COMPONENT CREATED </> 🤙\n");

                if ($class) {
                    // Customize class path display for modules
                    if ($module) {
                        $classNamespace = $this->parser->classNamespace();
                        $className = $this->parser->className();
                        $classDisplayPath = str_replace('\\', '/', $classNamespace) . '/' . $className . '.php';
                        $this->line("<options=bold;fg=green>CLASS:</> {$classDisplayPath}");
                    } else {
                        $this->line("<options=bold;fg=green>CLASS:</> {$this->parser->relativeClassPath()}");
                    }
                }

                if (! $inline) {
                    $view && $this->line("<options=bold;fg=green>VIEW:</>  {$this->parser->relativeViewPath()}");
                }

                if ($test) {
                    $test && $this->line("<options=bold;fg=green>TEST:</>  {$this->parser->relativeTestPath()}");
                }

                if ($showWelcomeMessage && ! app()->runningUnitTests()) {
                    $this->writeWelcomeMessage();
                }
            }
        }

        protected function createClass($force = false, $inline = false)
        {
            if ($module = $this->module()) {
                $name = Str::of($this->argument('name'))
                    ->split('/[.\/(\\\\)]+/')
                    ->map([Str::class, 'studly'])
                    ->join(DIRECTORY_SEPARATOR);

                $classPath = $module->path('src/Livewire/' . $name . '.php');

                if (File::exists($classPath) && ! $force) {
                    $this->line("<options=bold,reverse;fg=red> WHOOPS-IE-TOOTLES </> 😳 \n");
                    $this->line("<fg=red;options=bold>Class already exists:</> {$this->parser->relativeClassPath()}");

                    return false;
                }

                $this->ensureDirectoryExists($classPath);

                $classContents = $this->parser->classContents($inline);

                // Fix the view path to use dot notation relative to module views
                $viewNameParts = Str::of($name)
                    ->explode(DIRECTORY_SEPARATOR)
                    ->map(fn($part) => Str::kebab($part))
                    ->implode('.');

                $correctViewName = Str::kebab($module->name) . '::livewire.' . $viewNameParts;

                $classContents = preg_replace(
                    "/return view\('(.+?)'\);/",
                    "return view('" . $correctViewName . "');",
                    $classContents
                );

                File::put($classPath, $classContents);

                $component_name = Str::of($name)
                    ->explode('/')
                    ->filter()
                    ->map([Str::class, 'kebab'])
                    ->implode('.');

                $fully_qualified_component = Str::of($this->argument('name'))
                    ->prepend('Livewire/')
                    ->split('/[.\/(\\\\)]+/')
                    ->map([Str::class, 'studly'])
                    ->join('\\');

                Livewire::component("{$module->name}::{$component_name}", $module->qualify($fully_qualified_component));

                return $classPath;
            }

            return parent::createClass($force, $inline);
        }
    }
}
