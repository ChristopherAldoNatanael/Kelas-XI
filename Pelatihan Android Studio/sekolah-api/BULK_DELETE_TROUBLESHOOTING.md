# Bulk Delete Troubleshooting Guide

This document provides a step-by-step approach to diagnose and resolve issues encountered when using the bulk delete features ("Delete Selected" and "Delete All Users") in the Users Management system.

---

## 1. Confirm Frontend Request Behavior

-   **Open browser developer tools (F12).**
-   Go to the **Console** tab and ensure no JavaScript errors appear when selecting users or clicking delete buttons.
-   Go to the **Network** tab.
-   Trigger a bulk delete ("Delete Selected" or "Delete All Users").
-   Check if a **DELETE** request is sent to the correct URL endpoints:
    -   `/web-users/bulk-delete` for selected users.
    -   `/web-users/bulk-delete-all` for deleting all.
-   Confirm that the requests include:
    -   Correct HTTP headers:
        -   `X-CSRF-TOKEN` with valid CSRF token.
        -   `Content-Type: application/json`
    -   Request body for selected delete contains `user_ids` array.
-   Note the HTTP response status and response payload.
-   Any response status other than 200/204 or indication of errors should be noted.

## 2. Verify CSRF Token Setup

-   Confirm the HTML `<meta name="csrf-token" content="...">` tag is present in the page head.
-   Confirm the JavaScript fetch requests attach the CSRF token exactly as shown in the blade template.
-   Any missing or mismatch here will cause 419 CSRF token errors.

## 3. Backend Checks

-   Review Laravel logs (`storage/logs/laravel.log`) for any exceptions or errors when bulk delete requests are made.
-   Confirm that the authenticated user has permission to perform these DELETE actions.
-   Confirm that middleware such as `auth` is correctly applied to the routes.

## 4. Expected Behavior

-   On successful deletion, the frontend should receive JSON `{ "success": true, "message": "...", "deleted_count": n }`.
-   On failure, JSON with `success: false` and error message is returned.
-   Frontend UI should update accordingly, removing deleted user cards and showing notifications.

## 5. Common Issues and Fixes

| Problem                         | Cause                                      | Fix                                                |
| ------------------------------- | ------------------------------------------ | -------------------------------------------------- |
| No request on clicking button   | JS event listener missing or errored       | Verify JS script is loaded, no console errors      |
| 419 status (CSRF token missing) | CSRF token missing or incorrect in request | Ensure meta tag and request header inclusion       |
| 403 forbidden                   | Authorization middleware rejects request   | Check user permissions and middleware settings     |
| 500 or 4xx server error         | Backend exception or validation failure    | Inspect Laravel logs and fix errors                |
| Frontend UI not updating        | JS error or logic issue after response     | Add debugging console logs, verify DOM update code |

---

## 6. Recommended Next Steps

-   Run through the above checks and gather error logs.
-   If no errors appear and requests are sent, but deletion does not occur, add debug logging in `bulkDelete` and `bulkDeleteAll` controller methods.
-   Test with CURL or Postman to directly invoke bulk delete routes to isolate frontend/backend issues.

---

## 7. Support

If you need further assistance, please provide:

-   Browser console error logs.
-   Network request details (headers, response codes, bodies).
-   Laravel log excerpts.
-   Authentication / permission setup details.

---

This guide aims to help identify and fix issues with bulk deletion operations efficiently.
