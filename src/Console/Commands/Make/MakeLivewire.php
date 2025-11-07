<?php

namespace InterNACHI\Modular\Console\Commands\Make;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportConsoleCommands\Commands\MakeCommand;
use Livewire\Livewire;
use Livewire\LivewireComponentsFinder;
use Symfony\Component\Console\Input\InputOption;

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
            if ($module = $this->module()) {
                Config::set('livewire.class_namespace', $module->qualify('Livewire'));
                Config::set('livewire.view_path', $module->path('resources/views/livewire/' . Str::kebab($module->name)));
            }

            parent::handle();
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

                $correctViewName = 'livewire.' . Str::kebab($module->name) . '.' . $viewNameParts;

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
