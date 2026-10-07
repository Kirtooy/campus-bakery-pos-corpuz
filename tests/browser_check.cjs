const { chromium } = require('playwright');
const path = require('path');

const baseUrl = process.argv[2] || 'http://127.0.0.1:8000';
const outputDirectory = path.join(__dirname, '..', 'screenshots');

function check(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

(async () => {
    const launchOptions = {headless: true};
    if (process.env.BROWSER_EXECUTABLE) {
        launchOptions.executablePath = process.env.BROWSER_EXECUTABLE;
    }
    const browser = await chromium.launch(launchOptions);
    const page = await browser.newPage({viewport: {width: 1366, height: 768}, deviceScaleFactor: 1});

    try {
        await page.goto(baseUrl, {waitUntil: 'networkidle'});
        check(await page.locator('[data-add-product]').count() === 6, 'Expected six product buttons.');
        await page.screenshot({path: path.join(outputDirectory, '01-product-list.png'), fullPage: true});

        await page.locator('[data-add-product="pandesal"]').click();
        await page.locator('[data-add-product="pandesal"]').click();
        await page.locator('[data-add-product="loaf-bread"]').click();
        await page.screenshot({path: path.join(outputDirectory, '02-cart-summary.png'), fullPage: true});

        check((await page.locator('#cart-total').textContent()).includes('105.00'), 'Cart total should be ₱105.00.');

        await page.locator('#cash-payment').fill('100');
        await page.locator('#pay-button').click();
        await page.locator('#payment-error').waitFor({state: 'visible'});
        check((await page.locator('#payment-error').textContent()).includes('Insufficient payment'), 'Insufficient payment message was not shown.');

        await page.locator('#cash-payment').fill('200');
        await page.locator('#pay-button').click();
        await page.locator('#receipt-dialog').waitFor({state: 'visible'});
        check((await page.locator('#receipt-change').textContent()).includes('95.00'), 'Receipt change should be ₱95.00.');
        check((await page.locator('#receipt-reference').textContent()).includes('CB-'), 'Receipt reference is missing.');
        await page.screenshot({path: path.join(outputDirectory, '03-payment-success.png'), fullPage: true});
        await page.locator('#receipt-dialog').screenshot({path: path.join(outputDirectory, '04-digital-receipt.png')});

        await page.locator('#new-transaction').click();
        check(await page.locator('#cart-empty').isVisible(), 'New transaction should clear the cart.');
        check(await page.locator('#cash-payment').inputValue() === '', 'New transaction should clear payment.');

        console.log('Browser flow passed: cart, validation, payment, receipt, and reset.');
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
