<?php
/**
 * Sports Camp Management System - Financial Calculations Unit Tests
 * 
 * Run with: php api/tests/FinancialCalculationsTest.php
 * 
 * Tests cover:
 * - JalaliDate leap year calculation
 * - JalaliDate days in month calculation
 * - TimeSlotDetector morning/evening detection
 * - Coach payment calculations (percentage, salary, hybrid)
 */

// Include required files
require_once __DIR__ . '/../utils.php';

// Simple test framework
class TestRunner {
    private $passed = 0;
    private $failed = 0;
    private $tests = [];
    
    public function test($name, $condition, $message = '') {
        if ($condition) {
            $this->passed++;
            $this->tests[] = ['name' => $name, 'passed' => true];
            echo "✅ PASS: {$name}\n";
        } else {
            $this->failed++;
            $this->tests[] = ['name' => $name, 'passed' => false, 'message' => $message];
            echo "❌ FAIL: {$name}" . ($message ? " - {$message}" : "") . "\n";
        }
    }
    
    public function assertEquals($expected, $actual, $name) {
        $condition = $expected === $actual;
        $message = $condition ? '' : "Expected: {$expected}, Got: {$actual}";
        $this->test($name, $condition, $message);
    }
    
    public function assertTrue($actual, $name) {
        $this->test($name, $actual === true, "Expected true, got " . var_export($actual, true));
    }
    
    public function assertFalse($actual, $name) {
        $this->test($name, $actual === false, "Expected false, got " . var_export($actual, true));
    }
    
    public function summary() {
        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Test Results: {$this->passed}/{$total} passed";
        if ($this->failed > 0) {
            echo " ({$this->failed} failed)";
        }
        echo "\n" . str_repeat("=", 50) . "\n";
        return $this->failed === 0;
    }
}

// =====================================================
// TEST: Jalali Leap Year Calculation
// =====================================================
function testJalaliLeapYear($runner) {
    echo "\n📅 Testing JalaliDate::isLeapYear()\n";
    echo str_repeat("-", 40) . "\n";
    
    // Known leap years in the 33-year cycle
    // Cycle positions: 1, 5, 9, 13, 17, 22, 26, 30
    
    // Year 1403 (1403 % 33 = 17) - should be leap year
    $runner->assertTrue(JalaliDate::isLeapYear(1403), '1403 should be a leap year (17 in cycle)');
    
    // Year 1404 (1404 % 33 = 18) - should NOT be leap year
    $runner->assertFalse(JalaliDate::isLeapYear(1404), '1404 should NOT be a leap year (18 in cycle)');
    
    // Year 1399 (1399 % 33 = 13) - should be leap year
    $runner->assertTrue(JalaliDate::isLeapYear(1399), '1399 should be a leap year (13 in cycle)');
    
    // Year 1400 (1400 % 33 = 14) - should NOT be leap year
    $runner->assertFalse(JalaliDate::isLeapYear(1400), '1400 should NOT be a leap year (14 in cycle)');
    
    // Year 1407 (1407 % 33 = 21) - should NOT be leap year
    $runner->assertFalse(JalaliDate::isLeapYear(1407), '1407 should NOT be a leap year (21 in cycle)');
    
    // Year 1408 (1408 % 33 = 22) - should be leap year
    $runner->assertTrue(JalaliDate::isLeapYear(1408), '1408 should be a leap year (22 in cycle)');
}

// =====================================================
// TEST: Jalali Days in Month Calculation
// =====================================================
function testJalaliDaysInMonth($runner) {
    echo "\n📆 Testing JalaliDate::getDaysInMonth()\n";
    echo str_repeat("-", 40) . "\n";
    
    // Months 1-6 should have 31 days
    for ($m = 1; $m <= 6; $m++) {
        $runner->assertEquals(31, JalaliDate::getDaysInMonth($m, 1403), "Month {$m} should have 31 days");
    }
    
    // Months 7-11 should have 30 days
    for ($m = 7; $m <= 11; $m++) {
        $runner->assertEquals(30, JalaliDate::getDaysInMonth($m, 1403), "Month {$m} should have 30 days");
    }
    
    // Month 12 in leap year (1403) should have 30 days
    $runner->assertEquals(30, JalaliDate::getDaysInMonth(12, 1403), 'Month 12 in leap year 1403 should have 30 days');
    
    // Month 12 in non-leap year (1404) should have 29 days
    $runner->assertEquals(29, JalaliDate::getDaysInMonth(12, 1404), 'Month 12 in non-leap year 1404 should have 29 days');
}

// =====================================================
// TEST: Month Date Range Calculation
// =====================================================
function testMonthDateRange($runner) {
    echo "\n📅 Testing JalaliDate::getMonthDateRange()\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test month 1 of 1403
    $range = JalaliDate::getMonthDateRange(1403, 1);
    $runner->assertEquals('1403-01-01', $range['start'], 'Month 1 start date should be 1403-01-01');
    $runner->assertEquals('1403-01-31', $range['end'], 'Month 1 end date should be 1403-01-31');
    
    // Test month 7 (30 days)
    $range = JalaliDate::getMonthDateRange(1403, 7);
    $runner->assertEquals('1403-07-01', $range['start'], 'Month 7 start date should be 1403-07-01');
    $runner->assertEquals('1403-07-30', $range['end'], 'Month 7 end date should be 1403-07-30');
    
    // Test month 12 in leap year (1403)
    $range = JalaliDate::getMonthDateRange(1403, 12);
    $runner->assertEquals('1403-12-30', $range['end'], 'Month 12 in leap year should end on 30th');
    
    // Test month 12 in non-leap year (1404)
    $range = JalaliDate::getMonthDateRange(1404, 12);
    $runner->assertEquals('1404-12-29', $range['end'], 'Month 12 in non-leap year should end on 29th');
}

// =====================================================
// TEST: Next Month Calculation
// =====================================================
function testNextMonth($runner) {
    echo "\n➡️ Testing JalaliDate::getNextMonth()\n";
    echo str_repeat("-", 40) . "\n";
    
    // Regular month progression
    $next = JalaliDate::getNextMonth(1403, 5);
    $runner->assertEquals(1403, $next['year'], 'Year should stay 1403 for month 5->6');
    $runner->assertEquals(6, $next['month'], 'Month should be 6 after month 5');
    
    // Year rollover
    $next = JalaliDate::getNextMonth(1403, 12);
    $runner->assertEquals(1404, $next['year'], 'Year should roll to 1404 from month 12');
    $runner->assertEquals(1, $next['month'], 'Month should be 1 after month 12');
}

// =====================================================
// TEST: Time Slot Detection
// =====================================================
function testTimeSlotDetection($runner) {
    echo "\n⏰ Testing TimeSlotDetector::isMorningEvening()\n";
    echo str_repeat("-", 40) . "\n";
    
    // English keywords
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('Morning Class'), 'Should detect "Morning Class"');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('evening session'), 'Should detect "evening session"');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('MORNING'), 'Should detect "MORNING" (case insensitive)');
    
    // Dari/Persian keywords
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('صبح'), 'Should detect "صبح" (morning)');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('وقت صبح'), 'Should detect "وقت صبح"');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('شام'), 'Should detect "شام" (evening)');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('عصر'), 'Should detect "عصر" (afternoon)');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('صبحانه'), 'Should detect "صبحانه" (breakfast/morning)');
    $runner->assertTrue(TimeSlotDetector::isMorningEvening('شب'), 'Should detect "شب" (night)');
    
    // Non-matching slots
    $runner->assertFalse(TimeSlotDetector::isMorningEvening('Afternoon'), 'Should NOT detect "Afternoon"');
    $runner->assertFalse(TimeSlotDetector::isMorningEvening('چاشت'), 'Should NOT detect "چاشت" (noon)');
    $runner->assertFalse(TimeSlotDetector::isMorningEvening('Special Class'), 'Should NOT detect "Special Class"');
}

// =====================================================
// TEST: Coach Payment Calculation
// =====================================================
function testCoachPaymentCalculation($runner) {
    echo "\n💰 Testing Coach Payment Calculations\n";
    echo str_repeat("-", 40) . "\n";
    
    // Include accounting.php functions (they're in a separate file)
    // For now, we'll test the logic inline
    
    // Test percentage contract
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 50,
        'monthly_salary' => 0,
        'eligible_fees' => 100000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(50000.0, $payment, 'Percentage: 100000 × 50% = 50000');
    
    // Test salary contract
    $coach = [
        'contract_type' => 'salary',
        'percentage_rate' => 50,
        'monthly_salary' => 25000,
        'eligible_fees' => 100000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(25000.0, $payment, 'Salary: Fixed 25000');
    
    // Test hybrid contract
    $coach = [
        'contract_type' => 'hybrid',
        'percentage_rate' => 30,
        'monthly_salary' => 10000,
        'eligible_fees' => 50000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $expected = 10000 + (50000 * 0.30); // 10000 + 15000 = 25000
    $runner->assertEquals(25000.0, $payment, 'Hybrid: 10000 + (50000 × 30%) = 25000');
    
    // Test edge cases
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 0,
        'monthly_salary' => 0,
        'eligible_fees' => 100000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(0.0, $payment, 'Percentage with 0% rate = 0');
    
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 100,
        'monthly_salary' => 0,
        'eligible_fees' => 50000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(50000.0, $payment, 'Percentage with 100% rate = full amount');
    
    // Test unknown contract type
    $coach = [
        'contract_type' => 'unknown',
        'percentage_rate' => 50,
        'monthly_salary' => 25000,
        'eligible_fees' => 100000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(0.0, $payment, 'Unknown contract type = 0');
}

/**
 * Calculate coach payment (copy of function from accounting.php for testing)
 */
function calculateCoachPaymentTest($coach) {
    $contractType = $coach['contract_type'] ?? 'percentage';
    $percentageRate = (float)($coach['percentage_rate'] ?? 50);
    $monthlySalary = (float)($coach['monthly_salary'] ?? 0);
    $eligibleFees = (float)($coach['eligible_fees'] ?? 0);
    
    switch ($contractType) {
        case 'salary':
            return $monthlySalary;
            
        case 'percentage':
            return $eligibleFees * ($percentageRate / 100);
            
        case 'hybrid':
            $percentagePart = $eligibleFees * ($percentageRate / 100);
            return $monthlySalary + $percentagePart;
            
        default:
            return 0.0;
    }
}

// =====================================================
// TEST: Money Class Calculations
// =====================================================
function testMoneyCalculations($runner) {
    echo "\n💵 Testing Money Class\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test rounding - banker's rounding rounds to nearest even on tie
    $runner->assertEquals(100.12, Money::round(100.1234), 'Round 100.1234 to 100.12');
    $runner->assertEquals(100.12, Money::round(100.125), 'Round 100.125 to 100.12 (banker\'s rounding - ties to even)');
    $runner->assertEquals(100.12, Money::round(100.115), 'Round 100.115 to 100.12 (banker\'s rounding)');
    $runner->assertEquals(100.14, Money::round(100.135), 'Round 100.135 to 100.14 (banker\'s rounding - ties to even)');
    
    // Test percentage calculation
    $runner->assertEquals(50.00, Money::percentage(100, 50), '50% of 100 = 50');
    $runner->assertEquals(33.33, Money::percentage(100, 33.33), '33.33% of 100 = 33.33');
    $runner->assertEquals(0.00, Money::percentage(100, 0), '0% of 100 = 0');
    $runner->assertEquals(100.00, Money::percentage(100, 100), '100% of 100 = 100');
    
    // Test floating-point precision (the main issue we're solving)
    $result = Money::percentage(99999, 33.333333);
    $runner->assertTrue($result == round($result, 2), 'Percentage result should have max 2 decimal places');
    
    // Test add
    $runner->assertEquals(300.00, Money::add(100, 200), 'Add 100 + 200 = 300');
    $runner->assertEquals(100.02, Money::add(100.006, 0.009), 'Add with rounding');
    $runner->assertEquals(600.00, Money::add(100, 200, 300), 'Add multiple values');
    
    // Test subtract
    $runner->assertEquals(50.00, Money::subtract(100, 50), 'Subtract 100 - 50 = 50');
    $runner->assertEquals(-50.00, Money::subtract(50, 100), 'Subtract 50 - 100 = -50');
    $runner->assertEquals(50.00, Money::subtract(200, 100, 50), 'Subtract multiple: 200 - 100 - 50 = 50');
    
    // Test equals
    $runner->assertTrue(Money::equals(100.00, 100.00), '100.00 equals 100.00');
    $runner->assertTrue(Money::equals(100.00, 100.005), '100.00 equals 100.005 within tolerance');
    $runner->assertFalse(Money::equals(100.00, 100.02), '100.00 does not equal 100.02');
    $runner->assertFalse(Money::equals(100, 200), '100 does not equal 200');
    
    // Test format
    $runner->assertEquals('1,000', Money::format(1000), 'Format 1000 as 1,000');
    $runner->assertEquals('1,234,567', Money::format(1234567), 'Format large number');
}

// =====================================================
// TEST: Edge Cases and Validation
// =====================================================
function testEdgeCases($runner) {
    echo "\n⚠️ Testing Edge Cases\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test with zero values
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 50,
        'eligible_fees' => 0
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(0.0, $payment, 'Zero eligible fees = zero payment');
    
    // Test with negative values (shouldn't happen but should handle gracefully)
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 50,
        'eligible_fees' => -1000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(-500.0, $payment, 'Negative fees produce negative payment');
    
    // Test hybrid with zero salary
    $coach = [
        'contract_type' => 'hybrid',
        'percentage_rate' => 50,
        'monthly_salary' => 0,
        'eligible_fees' => 1000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(500.0, $payment, 'Hybrid with zero salary = percentage only');
    
    // Test hybrid with zero percentage
    $coach = [
        'contract_type' => 'hybrid',
        'percentage_rate' => 0,
        'monthly_salary' => 1000,
        'eligible_fees' => 5000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(1000.0, $payment, 'Hybrid with zero percentage = salary only');
    
    // Test missing contract type defaults to percentage
    $coach = [
        'percentage_rate' => 50,
        'eligible_fees' => 1000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(500.0, $payment, 'Missing contract_type defaults to percentage');
    
    // Test with very large numbers
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 33.333333,
        'eligible_fees' => 999999999
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertTrue(is_float($payment), 'Large number calculation returns float');
    $runner->assertTrue($payment > 0, 'Large number calculation is positive');
    
    // Test decimal percentage rate
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => 33.5,
        'eligible_fees' => 10000
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(3350.0, $payment, 'Decimal percentage: 33.5% of 10000 = 3350');
}

// =====================================================
// TEST: JalaliDate Validation
// =====================================================
function testJalaliValidation($runner) {
    echo "\n✅ Testing JalaliDate Validation\n";
    echo str_repeat("-", 40) . "\n";
    
    // Valid dates
    $runner->assertTrue(JalaliDate::validate('1403-01-15'), 'Valid date 1403-01-15');
    $runner->assertTrue(JalaliDate::validate('1403-06-31'), 'Valid date 1403-06-31 (31 days)');
    $runner->assertTrue(JalaliDate::validate('1403-07-30'), 'Valid date 1403-07-30 (30 days)');
    $runner->assertTrue(JalaliDate::validate('1403-12-30'), 'Valid leap year last day');
    
    // Invalid dates
    $runner->assertFalse(JalaliDate::validate(''), 'Empty date is invalid');
    $runner->assertFalse(JalaliDate::validate('invalid'), 'Non-date string is invalid');
    $runner->assertFalse(JalaliDate::validate('1403-13-01'), 'Month 13 is invalid');
    $runner->assertFalse(JalaliDate::validate('1403-00-01'), 'Month 0 is invalid');
    $runner->assertFalse(JalaliDate::validate('1403-01-32'), 'Day 32 is invalid');
    $runner->assertFalse(JalaliDate::validate('1403-07-31'), 'Month 7 cannot have 31 days');
    $runner->assertFalse(JalaliDate::validate('1200-01-01'), 'Year 1200 is out of range');
    $runner->assertFalse(JalaliDate::validate('1600-01-01'), 'Year 1600 is out of range');
}

// =====================================================
// TEST: Floating Point Precision Issues
// =====================================================
function testFloatingPointPrecision($runner) {
    echo "\n🔢 Testing Floating Point Precision\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test that we don't get floating point errors like 33330.00000000001
    $result = Money::percentage(99990, 33.33);
    $asString = (string)$result;
    $runner->assertTrue(
        strlen($asString) < 15, 
        'Percentage result should not have floating point precision errors'
    );
    
    // Test multiple operations don't accumulate errors
    $amount = 100.00;
    for ($i = 0; $i < 10; $i++) {
        $amount = Money::add($amount, 0.10);
    }
    $runner->assertEquals(101.00, $amount, 'Adding 0.10 ten times should equal 101.00');
    
    // Test division and multiplication precision
    $original = 100.00;
    $divided = Money::percentage($original, 33.333333);
    $multiplied = Money::percentage($divided, 300);
    // Should be close to 100 (33.33 * 3 = ~100)
    $runner->assertTrue(
        abs($multiplied - $original) < 0.02, 
        'Division and multiplication should not lose precision'
    );
    
    // Edge case: very small percentages
    $result = Money::percentage(1000000, 0.01);
    $runner->assertEquals(100.00, $result, '0.01% of 1,000,000 = 100');
    
    // Edge case: percentages > 100
    $result = Money::percentage(100, 150);
    $runner->assertEquals(150.00, $result, '150% of 100 = 150');
}

// =====================================================
// TEST: Category Case Sensitivity
// =====================================================
function testCategoryCaseSensitivity($runner) {
    echo "\n🏷️ Testing Category Case Handling\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test that strtolower works correctly for category comparison
    $categories = ['Rent', 'rent', 'RENT', 'ReNt'];
    foreach ($categories as $cat) {
        $normalized = strtolower($cat);
        $runner->assertEquals('rent', $normalized, "'{$cat}' should normalize to 'rent'");
    }
    
    // Test Dari/Persian category names (they don't have case, but should work)
    $dariCategories = ['اجاره', 'آب', 'برق'];
    foreach ($dariCategories as $cat) {
        $normalized = mb_strtolower($cat, 'UTF-8');
        $runner->assertEquals($cat, $normalized, "Dari category '{$cat}' should remain unchanged");
    }
}

// =====================================================
// TEST: Date Boundary Conditions
// =====================================================
function testDateBoundaries($runner) {
    echo "\n📅 Testing Date Boundary Conditions\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test first month of year
    $range = JalaliDate::getMonthDateRange(1403, 1);
    $runner->assertEquals('1403-01-01', $range['start'], 'First month starts on 01');
    $runner->assertEquals('1403-01-31', $range['end'], 'First month ends on 31');
    
    // Test last month of year (non-leap)
    $range = JalaliDate::getMonthDateRange(1404, 12);
    $runner->assertEquals('1404-12-01', $range['start'], 'Last month starts on 01');
    $runner->assertEquals('1404-12-29', $range['end'], 'Last month (non-leap) ends on 29');
    
    // Test year transition
    $next = JalaliDate::getNextMonth(1403, 12);
    $runner->assertEquals(1404, $next['year'], 'Year increments from 1403 to 1404');
    $runner->assertEquals(1, $next['month'], 'Month resets to 1');
    
    // Test multiple year transitions
    $year = 1400;
    $month = 1;
    for ($i = 0; $i < 24; $i++) { // 2 years worth
        $next = JalaliDate::getNextMonth($year, $month);
        $year = $next['year'];
        $month = $next['month'];
    }
    $runner->assertEquals(1402, $year, 'After 24 months from 1400/01, year should be 1402');
    $runner->assertEquals(1, $month, 'After 24 months from 1400/01, month should be 1');
}

// =====================================================
// TEST: Empty and Null Handling
// =====================================================
function testEmptyAndNullHandling($runner) {
    echo "\n⚠️ Testing Empty/Null Handling\n";
    echo str_repeat("-", 40) . "\n";
    
    // Coach payment with empty array
    $coach = [];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(0.0, $payment, 'Empty coach array should return 0');
    
    // Coach payment with null values (will be cast to 0)
    $coach = [
        'contract_type' => 'percentage',
        'percentage_rate' => null,
        'eligible_fees' => null
    ];
    $payment = calculateCoachPaymentTest($coach);
    $runner->assertEquals(0.0, $payment, 'Null values should be treated as 0');
    
    // Money operations with zero
    $runner->assertEquals(0.0, Money::round(0), 'Rounding 0 should return 0');
    $runner->assertEquals(0.0, Money::percentage(0, 50), '50% of 0 = 0');
    $runner->assertEquals(0.0, Money::percentage(100, 0), '0% of 100 = 0');
    $runner->assertEquals(0.0, Money::add(0, 0, 0), 'Adding zeros = 0');
    
    // Time slot with empty string
    $runner->assertFalse(TimeSlotDetector::isMorningEvening(''), 'Empty string should return false');
}

// =====================================================
// RUN ALL TESTS
// =====================================================
echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║  Sports Camp Financial Calculations Test Suite   ║\n";
echo "╚══════════════════════════════════════════════════╝\n";

$runner = new TestRunner();

testJalaliLeapYear($runner);
testJalaliDaysInMonth($runner);
testMonthDateRange($runner);
testNextMonth($runner);
testTimeSlotDetection($runner);
testCoachPaymentCalculation($runner);
testMoneyCalculations($runner);
testEdgeCases($runner);
testJalaliValidation($runner);
testFloatingPointPrecision($runner);
testCategoryCaseSensitivity($runner);
testDateBoundaries($runner);
testEmptyAndNullHandling($runner);

$success = $runner->summary();

exit($success ? 0 : 1);

