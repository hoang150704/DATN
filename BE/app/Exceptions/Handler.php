<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */

    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace()
            ], 500);
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return response()->json([
                'message' => 'Bạn đã gửi quá nhiều yêu cầu, vui lòng thử lại sau!'
            ], 429);
        }

        return parent::render($request, $exception);
    }


    public function shouldReturnJson($request, Throwable $e)
    {
        return true;
    }

}
