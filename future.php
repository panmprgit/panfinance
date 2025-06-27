<?php
/**
 * Lightweight Future implementation using Fibers if available.
 * Falls back to synchronous execution when Fibers are not supported.
 */
class Future {
    private $fiber = null;
    private $result = null;
    private $error = null;

    public function __construct(callable $callback) {
        if (class_exists('Fiber')) {
            $this->fiber = new Fiber(function() use ($callback) {
                return $callback();
            });
            $this->fiber->start();
        } else {
            try {
                $this->result = $callback();
            } catch (\Throwable $e) {
                $this->error = $e;
            }
        }
    }

    public function await() {
        if ($this->fiber) {
            try {
                while ($this->fiber->isSuspended()) {
                    $this->fiber->resume();
                }
                $this->result = $this->fiber->getReturn();
            } catch (\Throwable $e) {
                $this->error = $e;
            }
        }
        if ($this->error) {
            throw $this->error;
        }
        return $this->result;
    }
}

function async(callable $fn): Future {
    return new Future($fn);
}
