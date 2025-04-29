import './bootstrap';
import picker from "./picker.js";
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import Tooltip from "@ryangjchandler/alpine-tooltip";

Alpine.plugin(Tooltip);
Alpine.plugin(picker);
Livewire.start();
